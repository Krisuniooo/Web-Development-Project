<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * A small WebSocket client (RFC 6455), enough for the Discord Gateway.
 * It sends text messages and receives text and binary ones.
 */
final class WebSocketClient
{
    // the server joins our key with this fixed text, which proves it really speaks WebSocket
    private const HANDSHAKE_GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
    // random bytes in the key of a handshake, as the protocol asks
    private const KEY_BYTES = 16;
    private const DEFAULT_PORT = 80;
    private const DEFAULT_SECURE_PORT = 443;
    private const TIMEOUT_SECONDS = 10;
    // a bigger message is treated as an error, so a broken server cannot use up the memory
    private const MAX_MESSAGE_BYTES = 16 * 1024 * 1024;
    // the same for the headers of the server's answer to the handshake
    private const MAX_HANDSHAKE_BYTES = 16 * 1024;
    private const READ_CHUNK_BYTES = 64 * 1024;
    // how much of a refused handshake's status line goes into the error
    private const STATUS_LINE_LENGTH = 100;

    // the first two bytes of a frame (RFC 6455, section 5.2): the final flag, the reserved bits and the type,
    // then the mask flag and the length
    private const HEADER_BYTES = 2;
    private const FINAL_BIT = 0x80;
    private const RESERVED_BITS = 0x70;
    private const OPCODE_BITS = 0x0F;
    private const MASK_BIT = 0x80;
    private const LENGTH_BITS = 0x7F;
    // these lengths say that the real one follows in the next 2 or 8 bytes
    private const LENGTH_16_BIT = 126;
    private const LENGTH_64_BIT = 127;
    private const LENGTH_16_BIT_BYTES = 2;
    private const LENGTH_64_BIT_BYTES = 8;
    private const MAX_16_BIT_LENGTH = 0xFFFF;
    // a client masks every frame it sends with this many random bytes
    private const MASK_BYTES = 4;

    private const OPCODE_CONTINUATION = 0x0;
    private const OPCODE_TEXT = 0x1;
    private const OPCODE_BINARY = 0x2;
    private const OPCODE_CLOSE = 0x8;
    private const OPCODE_PING = 0x9;
    private const OPCODE_PONG = 0xA;

    // close codes (RFC 6455, section 7.4.1): a normal end, and the one that stands for a close frame without a code;
    // a close frame gives its code in its first two bytes
    private const CLOSE_NORMAL = 1000;
    private const CLOSE_NO_CODE = 1005;
    private const CLOSE_CODE_BYTES = 2;

    /** @var resource */
    private $socket;
    // bytes already read from the connection but not used yet
    private string $buffer;
    // the parts of a message that arrives split into several frames, null between messages
    private ?string $fragments = null;

    /** @param resource $socket */
    private function __construct($socket, string $buffer)
    {
        $this->socket = $socket;
        $this->buffer = $buffer;
    }

    /** Opens the connection and upgrades it from HTTP to WebSocket. */
    public static function connect(string $url): self
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? '';
        $host = $parts['host'] ?? '';

        if (!in_array($scheme, ['ws', 'wss'], true) || $host === '') {
            throw new RuntimeException("Not a WebSocket address: $url");
        }

        $secure = $scheme === 'wss';
        $port = $parts['port'] ?? ($secure ? self::DEFAULT_SECURE_PORT : self::DEFAULT_PORT);
        $path = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');

        $socket = @stream_socket_client(
            ($secure ? 'tls://' : 'tcp://') . "$host:$port",
            $errorCode,
            $errorMessage,
            self::TIMEOUT_SECONDS,
            STREAM_CLIENT_CONNECT,
            // the certificate is checked, so nobody in between can pretend to be the server
            stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]),
        );

        if ($socket === false) {
            // PHP leaves the message empty when the certificate check fails
            $reason = $errorMessage !== '' ? $errorMessage : 'the connection or the certificate check failed';
            throw new RuntimeException("Cannot connect to $host:$port: $reason");
        }

        stream_set_timeout($socket, self::TIMEOUT_SECONDS);

        // a random key for this connection, which the server must transform and send back
        $key = base64_encode(random_bytes(self::KEY_BYTES));

        self::writeAll($socket, implode("\r\n", [
            "GET $path HTTP/1.1",
            'Host: ' . $host . (isset($parts['port']) ? ":$port" : ''),
            'Upgrade: websocket',
            'Connection: Upgrade',
            "Sec-WebSocket-Key: $key",
            'Sec-WebSocket-Version: 13',
            '',
            '',
        ]));

        $response = '';

        while (!str_contains($response, "\r\n\r\n")) {
            $chunk = fread($socket, self::READ_CHUNK_BYTES);

            if ($chunk === false || $chunk === '' || strlen($response) > self::MAX_HANDSHAKE_BYTES) {
                fclose($socket);
                throw new RuntimeException('The server did not finish the WebSocket handshake');
            }

            $response .= $chunk;
        }

        // the server may send its first frame right after the headers, so the rest is kept
        [$head, $rest] = explode("\r\n\r\n", $response, 2);
        $lines = explode("\r\n", $head);

        if (!preg_match('~^HTTP/1\.1 101\b~', $lines[0])) {
            fclose($socket);
            throw new RuntimeException('The server refused the WebSocket upgrade: ' . substr($lines[0], 0, self::STATUS_LINE_LENGTH));
        }

        $accept = null;

        foreach (array_slice($lines, 1) as $line) {
            [$name, $value] = array_pad(explode(':', $line, 2), 2, '');

            if (strcasecmp(trim($name), 'Sec-WebSocket-Accept') === 0) {
                $accept = trim($value);
            }
        }

        if ($accept !== base64_encode(sha1($key . self::HANDSHAKE_GUID, true))) {
            fclose($socket);
            throw new RuntimeException('The server answered the handshake with a wrong key');
        }

        // from now on reading never blocks: receive() decides how long to wait
        stream_set_blocking($socket, false);

        return new self($socket, $rest);
    }

    public function send(string $text): void
    {
        $this->sendFrame(self::OPCODE_TEXT, $text);
    }

    /**
     * Waits up to $timeoutSeconds for the next message and returns it, or null when the time runs out.
     * Pings are answered on the way. When the server closes the connection, the exception code is its close code.
     */
    public function receive(float $timeoutSeconds): ?string
    {
        $deadline = microtime(true) + $timeoutSeconds;

        while (true) {
            $frame = $this->takeFrame();

            if ($frame === null) {
                if (!$this->readMore($deadline)) {
                    return null;
                }

                continue;
            }

            [$isFinal, $opcode, $payload] = $frame;

            if ($opcode === self::OPCODE_PING) {
                $this->sendFrame(self::OPCODE_PONG, $payload);
            } elseif ($opcode === self::OPCODE_PONG) {
                // an answer to a ping we never send, so there is nothing to do
            } elseif ($opcode === self::OPCODE_CLOSE) {
                $hasCode = strlen($payload) >= self::CLOSE_CODE_BYTES;
                $code = $hasCode ? unpack('n', $payload)[1] : self::CLOSE_NO_CODE;
                $reason = substr($payload, self::CLOSE_CODE_BYTES);
                // the protocol asks to answer a close with a close before hanging up
                $this->close($hasCode ? $code : null);
                throw new RuntimeException("The server closed the connection: $code $reason", $code);
            } elseif ($opcode === self::OPCODE_TEXT || $opcode === self::OPCODE_BINARY) {
                if ($this->fragments !== null) {
                    throw new RuntimeException('A new message started before the previous one ended');
                }

                if ($isFinal) {
                    return $payload;
                }

                $this->fragments = $payload;
            } elseif ($opcode === self::OPCODE_CONTINUATION) {
                if ($this->fragments === null) {
                    throw new RuntimeException('A continuation frame came without a message to continue');
                }

                $this->fragments .= $payload;

                if (strlen($this->fragments) > self::MAX_MESSAGE_BYTES) {
                    throw new RuntimeException('The message is too large');
                }

                if ($isFinal) {
                    $message = $this->fragments;
                    $this->fragments = null;

                    return $message;
                }
            } else {
                throw new RuntimeException("Unknown WebSocket frame type $opcode");
            }
        }
    }

    /** Ends the connection politely, with a close code the server can read. */
    public function close(?int $code = self::CLOSE_NORMAL): void
    {
        if (!is_resource($this->socket)) {
            return;
        }

        try {
            $this->sendFrame(self::OPCODE_CLOSE, $code === null ? '' : pack('n', $code));
        } catch (RuntimeException) {
            // the connection may already be gone, and it is being closed anyway
        }

        fclose($this->socket);
    }

    /**
     * Cuts one whole frame off the front of the buffer.
     *
     * @return array{bool, int, string}|null the final flag, the type and the payload, or null when more bytes are needed
     */
    private function takeFrame(): ?array
    {
        if (strlen($this->buffer) < self::HEADER_BYTES) {
            return null;
        }

        $first = ord($this->buffer[0]);
        $second = ord($this->buffer[1]);

        if (($first & self::RESERVED_BITS) !== 0) {
            throw new RuntimeException('The server used a WebSocket extension that was not agreed on');
        }

        if (($second & self::MASK_BIT) !== 0) {
            throw new RuntimeException('The server masked a frame, which only clients may do');
        }

        $isFinal = ($first & self::FINAL_BIT) !== 0;
        $opcode = $first & self::OPCODE_BITS;
        $length = $second & self::LENGTH_BITS;
        $headerLength = self::HEADER_BYTES;

        // a longer payload gives its real length in the next 2 or 8 bytes, as a big-endian number
        if ($length === self::LENGTH_16_BIT || $length === self::LENGTH_64_BIT) {
            [$format, $size] = $length === self::LENGTH_16_BIT
                ? ['n', self::LENGTH_16_BIT_BYTES]
                : ['J', self::LENGTH_64_BIT_BYTES];

            if (strlen($this->buffer) < $headerLength + $size) {
                return null;
            }

            $length = unpack($format, $this->buffer, $headerLength)[1];
            $headerLength += $size;
        }

        // checked before waiting for the payload, so a huge length is refused at once
        if ($length < 0 || $length > self::MAX_MESSAGE_BYTES) {
            throw new RuntimeException('The message is too large');
        }

        if (strlen($this->buffer) < $headerLength + $length) {
            return null;
        }

        $payload = substr($this->buffer, $headerLength, $length);
        $this->buffer = substr($this->buffer, $headerLength + $length);

        return [$isFinal, $opcode, $payload];
    }

    /** Adds new bytes to the buffer. Returns false when nothing came before the deadline. */
    private function readMore(float $deadline): bool
    {
        while (true) {
            if (!is_resource($this->socket)) {
                throw new RuntimeException('The connection is closed');
            }

            // reading comes first: TLS can hold bytes that stream_select does not see
            $chunk = fread($this->socket, self::READ_CHUNK_BYTES);

            if ($chunk === false) {
                throw new RuntimeException('Reading from the connection failed');
            }

            if ($chunk !== '') {
                $this->buffer .= $chunk;

                return true;
            }

            if (feof($this->socket)) {
                fclose($this->socket);
                throw new RuntimeException('The connection dropped without a WebSocket close');
            }

            $secondsLeft = $deadline - microtime(true);

            if ($secondsLeft <= 0) {
                return false;
            }

            // sleeps until bytes arrive or the time runs out
            $read = [$this->socket];
            $write = null;
            $except = null;

            if (@stream_select($read, $write, $except, (int) $secondsLeft, (int) (fmod($secondsLeft, 1) * 1_000_000)) === false) {
                throw new RuntimeException('Waiting for the connection failed');
            }
        }
    }

    private function sendFrame(int $opcode, string $payload): void
    {
        if (!is_resource($this->socket)) {
            throw new RuntimeException('The connection is closed');
        }

        $length = strlen($payload);
        // messages are never split when sending, so every frame is the last of its message
        $header = chr(self::FINAL_BIT | $opcode);

        // and every frame is masked, which a client must always do
        if ($length < self::LENGTH_16_BIT) {
            $header .= chr(self::MASK_BIT | $length);
        } elseif ($length <= self::MAX_16_BIT_LENGTH) {
            $header .= chr(self::MASK_BIT | self::LENGTH_16_BIT) . pack('n', $length);
        } else {
            $header .= chr(self::MASK_BIT | self::LENGTH_64_BIT) . pack('J', $length);
        }

        // every byte of the payload is mixed with the random bytes of the mask, in turn
        $mask = random_bytes(self::MASK_BYTES);
        $masked = $payload ^ str_repeat($mask, intdiv($length + self::MASK_BYTES - 1, self::MASK_BYTES));

        self::writeAll($this->socket, $header . $mask . $masked);
    }

    /** @param resource $socket */
    private static function writeAll($socket, string $data): void
    {
        $deadline = microtime(true) + self::TIMEOUT_SECONDS;

        while ($data !== '') {
            $written = @fwrite($socket, $data);

            if ($written === false) {
                throw new RuntimeException('Writing to the connection failed');
            }

            $data = substr($data, $written);

            if ($data === '' || $written > 0) {
                continue;
            }

            // nothing went out because the send buffer is full, so this waits until there is room
            $secondsLeft = $deadline - microtime(true);
            $read = null;
            $write = [$socket];
            $except = null;

            if ($secondsLeft <= 0 || !@stream_select($read, $write, $except, (int) $secondsLeft, (int) (fmod($secondsLeft, 1) * 1_000_000))) {
                throw new RuntimeException('Writing to the connection timed out');
            }
        }
    }
}
