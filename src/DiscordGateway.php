<?php

declare(strict_types=1);

namespace App;

use Closure;
use RuntimeException;

/**
 * Keeps the bot connected to the Discord Gateway: logs in, sends heartbeats and connects again after a drop.
 * Every event, such as a new server, goes to $onEvent; an error thrown there ends the connection like any other.
 */
final class DiscordGateway
{
    // server events, such as joining or leaving a server
    private const INTENT_GUILDS = 1 << 0;
    // new messages on those servers; direct messages need another intent, which the bot does not ask for
    private const INTENT_GUILD_MESSAGES = 1 << 9;
    // the text of the messages; privileged, so Discord allows it only when it is turned on in the Developer Portal
    private const INTENT_MESSAGE_CONTENT = 1 << 15;

    private const OP_DISPATCH = 0;
    private const OP_HEARTBEAT = 1;
    private const OP_IDENTIFY = 2;
    private const OP_RECONNECT = 7;
    private const OP_INVALID_SESSION = 9;
    private const OP_HELLO = 10;
    private const OP_HEARTBEAT_ACK = 11;

    private const HELLO_TIMEOUT_SECONDS = 10;

    // Discord resets the token after 1000 logins in a day, so the pause between attempts grows up to this
    private const MAX_PAUSE_SECONDS = 120;
    // a connection that lasted this long was healthy, so the pause starts again from one second
    private const HEALTHY_SECONDS = 1800;

    // these come back after every new connection, so the bot stops and says what to fix
    private const FATAL_CLOSE_CODES = [
        4004 => 'Discord rejected the token. Copy a new one from the Developer Portal (Bot > Reset Token) into DISCORD_BOT_TOKEN',
        4010 => 'Discord rejected the shard settings',
        4011 => 'The bot is on too many servers for one connection and needs sharding',
        4012 => 'Discord no longer supports this Gateway version',
        4013 => 'Discord rejected the intents the bot asked for',
        4014 => 'Message Content Intent is turned off. Turn it on in the Developer Portal (Bot > Privileged Gateway Intents)',
    ];

    /** @param Closure(string, array): void $onEvent gets the event type, such as GUILD_CREATE, and its data */
    public function __construct(
        private string $token,
        private string $url,
        private Closure $onEvent,
    ) {
    }

    /** Stays connected until Discord refuses the bot for a reason that connecting again cannot fix. */
    public function run(): void
    {
        $pauseSeconds = 1;

        while (true) {
            $startedAt = time();

            try {
                $reason = $this->stayConnected();
            } catch (RuntimeException $error) {
                $closeCode = $error->getCode();

                if (isset(self::FATAL_CLOSE_CODES[$closeCode])) {
                    throw new RuntimeException(self::FATAL_CLOSE_CODES[$closeCode] . " (close code $closeCode)");
                }

                $reason = $error->getMessage();
            }

            if (time() - $startedAt >= self::HEALTHY_SECONDS) {
                $pauseSeconds = 1;
            }

            // one line, even when the reason has several, such as some database errors
            self::log("Disconnected, connecting again in $pauseSeconds s: " . preg_replace('/\s+/', ' ', $reason));
            sleep($pauseSeconds);
            $pauseSeconds = min($pauseSeconds * 2, self::MAX_PAUSE_SECONDS);
        }
    }

    /** One connection from start to end. Returns why it ended when Discord asks the bot to log in again. */
    private function stayConnected(): string
    {
        $socket = WebSocketClient::connect($this->url);

        try {
            // Discord speaks first and says how often it wants a heartbeat
            $message = $socket->receive(self::HELLO_TIMEOUT_SECONDS);
            $hello = $message === null ? null : self::decode($message);
            $interval = $hello['d']['heartbeat_interval'] ?? null;

            if (($hello['op'] ?? null) !== self::OP_HELLO || !is_int($interval) || $interval <= 0) {
                throw new RuntimeException('Discord did not start with Hello');
            }

            self::send($socket, self::OP_IDENTIFY, [
                'token' => $this->token,
                'intents' => self::INTENT_GUILDS | self::INTENT_GUILD_MESSAGES | self::INTENT_MESSAGE_CONTENT,
                'properties' => ['os' => 'linux', 'browser' => 'ChatWarden', 'device' => 'ChatWarden'],
            ]);

            // the number of the last event, which every heartbeat reports back
            $sequence = null;
            $acknowledged = true;
            // the first heartbeat goes at a random moment, so bots that start together do not all come at once
            $nextHeartbeat = microtime(true) + random_int(0, $interval) / 1000;

            while (true) {
                if (microtime(true) >= $nextHeartbeat) {
                    // no answer to the last heartbeat: the connection hangs, even if it looks open
                    if (!$acknowledged) {
                        throw new RuntimeException('Discord did not answer the last heartbeat');
                    }

                    self::send($socket, self::OP_HEARTBEAT, $sequence);
                    $acknowledged = false;
                    $nextHeartbeat = microtime(true) + $interval / 1000;
                }

                // waits for an event, but never past the time of the next heartbeat
                $message = $socket->receive($nextHeartbeat - microtime(true));

                if ($message === null) {
                    continue;
                }

                $event = self::decode($message);

                if (is_int($event['s'] ?? null)) {
                    $sequence = $event['s'];
                }

                if ($event['op'] === self::OP_HEARTBEAT_ACK) {
                    $acknowledged = true;
                } elseif ($event['op'] === self::OP_HEARTBEAT) {
                    // Discord can ask for a heartbeat right away
                    self::send($socket, self::OP_HEARTBEAT, $sequence);
                } elseif ($event['op'] === self::OP_RECONNECT) {
                    return 'Discord asked the bot to connect again';
                } elseif ($event['op'] === self::OP_INVALID_SESSION) {
                    return 'Discord ended the session';
                } elseif ($event['op'] === self::OP_DISPATCH) {
                    $type = $event['t'] ?? null;
                    $data = $event['d'] ?? null;

                    if (!is_string($type) || !is_array($data)) {
                        continue;
                    }

                    if ($type === 'READY') {
                        $name = $data['user']['username'] ?? null;
                        // READY lists every server the bot is on, their details come in later events
                        $servers = $data['guilds'] ?? null;
                        self::log(sprintf(
                            'Logged in as %s, servers: %d',
                            is_string($name) ? $name : 'an unknown name',
                            is_array($servers) ? count($servers) : 0,
                        ));
                    }

                    ($this->onEvent)($type, $data);
                }
            }
        } finally {
            $socket->close();
        }
    }

    private static function decode(string $message): array
    {
        $event = json_decode($message, true);

        if (!is_array($event) || !is_int($event['op'] ?? null)) {
            throw new RuntimeException('Discord sent a message the bot does not understand');
        }

        return $event;
    }

    private static function send(WebSocketClient $socket, int $op, mixed $data): void
    {
        $socket->send(json_encode(['op' => $op, 'd' => $data], JSON_THROW_ON_ERROR));
    }

    /** One line in the bot log, with the time. */
    public static function log(string $message): void
    {
        echo date('Y-m-d H:i:s') . " $message\n";
    }
}
