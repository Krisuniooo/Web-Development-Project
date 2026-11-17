<?php

declare(strict_types=1);

namespace App\Twitch;

use App\Log;
use App\WebSocketClient;
use Closure;
use RuntimeException;

/**
 * Keeps the bot connected to Twitch's EventSub, where the chat messages of the channels arrive: connects, subscribes to
 * the channels it is told to listen to, stops listening to those it no longer is, and connects again after a drop.
 * Every chat message goes to $onMessage; an error thrown there or in the other closures ends the connection like any other.
 * Twitch closes a connection that has no subscription, so one is open only while there is a channel to listen to.
 */
final class TwitchEventSub
{
    // Twitch's own address; a fake one comes from TWITCH_EVENTSUB_URL for tests
    public const URL = 'wss://eventsub.wss.twitch.tv/ws';
    // the domain of every address Twitch may move the bot to
    private const TWITCH_DOMAIN = 'twitch.tv';

    private const TYPE_CHAT_MESSAGE = 'channel.chat.message';

    // how often the channels to listen to are read from the database
    private const SYNC_SECONDS = 60;
    // how often the database is asked while there is no channel to listen to
    private const IDLE_CHECK_SECONDS = 5;
    // the longest the loop waits for a message, so the checks between them run at least this often
    private const MAX_WAIT_SECONDS = 1.0;
    // subscribing and unsubscribing are requests to Twitch that take a moment, so a few at a time,
    // and the connection is read in between
    private const REQUESTS_PER_ROUND = 10;
    // a channel Twitch refused, such as one beyond the 300 chats Twitch allows on a connection, waits this long
    // before it is asked for again, so a lasting refusal is not repeated and logged every minute
    private const REFUSED_RETRY_SECONDS = 300;
    // Twitch says how long it may be quiet in the welcome; until then, and a little longer, it is trusted
    private const DEFAULT_KEEPALIVE_SECONDS = 10;
    private const KEEPALIVE_MARGIN_SECONDS = 5;
    // the time Twitch gives a new connection to say hello
    private const WELCOME_TIMEOUT_SECONDS = 10;
    // how many ids of notifications are remembered, since Twitch may send one twice
    private const REMEMBERED_NOTIFICATIONS = 500;

    // the pause between attempts doubles, from the first up to the longest
    private const FIRST_PAUSE_SECONDS = 1;
    private const MAX_PAUSE_SECONDS = 120;
    // a connection that lasted this long was healthy, so the pause starts again from the first one
    private const HEALTHY_SECONDS = 1800;
    private const MICROSECONDS_PER_SECOND = 1_000_000;

    /**
     * @param Closure(): list<string> $wantedChannels the ids of the channels to listen to now
     * @param Closure(string, string): string $subscribe gets the session and a channel's id, and gives the id of the
     *     subscription; throws when Twitch refuses
     * @param Closure(string): void $unsubscribe gets the id of a subscription; throws when Twitch refuses
     * @param Closure(array): void $onMessage gets the event of each chat message
     * @param Closure(): float $betweenEvents runs between messages, such as the moderation queue, and must not wait;
     *     returns how many seconds may pass before it runs again
     */
    public function __construct(
        private string $url,
        private Closure $wantedChannels,
        private Closure $subscribe,
        private Closure $unsubscribe,
        private Closure $onMessage,
        private Closure $betweenEvents,
    ) {
    }

    /**
     * Whether the bot may move to this address when Twitch asks it to: one of Twitch's own over TLS, or the host the bot
     * was started with, such as a fake EventSub for tests. Every chat the bot moderates would come from there.
     */
    public static function isTrustedReconnectUrl(string $url, string $startUrl): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        // only the letters a real host name has, so nothing in it can make it look like Twitch's
        if (preg_match('/^[a-z0-9.-]+\z/', $host) !== 1) {
            return false;
        }

        $isTwitch = $scheme === 'wss' && ($host === self::TWITCH_DOMAIN || str_ends_with($host, '.' . self::TWITCH_DOMAIN));
        $isStartHost = $scheme === strtolower((string) parse_url($startUrl, PHP_URL_SCHEME))
            && $host === strtolower((string) parse_url($startUrl, PHP_URL_HOST));

        return $isTwitch || $isStartHost;
    }

    /** Stays connected for good: whatever goes wrong, it starts again after a pause that grows. */
    public function run(): never
    {
        $pauseSeconds = self::FIRST_PAUSE_SECONDS;

        while (true) {
            $startedAt = time();

            try {
                $reason = $this->stayConnected();
            } catch (RuntimeException $error) {
                $reason = $error->getMessage();
            }

            if (time() - $startedAt >= self::HEALTHY_SECONDS) {
                $pauseSeconds = self::FIRST_PAUSE_SECONDS;
            }

            // one line, even when the reason has several, such as some database errors
            Log::line("Disconnected, connecting again in $pauseSeconds s: " . preg_replace('/\s+/', ' ', $reason));
            sleep($pauseSeconds);
            $pauseSeconds = min($pauseSeconds * 2, self::MAX_PAUSE_SECONDS);
        }
    }

    /** Waits for a channel to listen to, then listens until the connection ends. Returns why it ended. */
    private function stayConnected(): string
    {
        $this->waitForChannels();

        return $this->listen();
    }

    /** Runs the work between messages while there is nothing to connect for. */
    private function waitForChannels(): void
    {
        $nextCheck = 0.0;

        while (true) {
            if (microtime(true) >= $nextCheck) {
                if (($this->wantedChannels)() !== []) {
                    return;
                }

                $nextCheck = microtime(true) + self::IDLE_CHECK_SECONDS;
            }

            usleep((int) (min(($this->betweenEvents)(), self::MAX_WAIT_SECONDS) * self::MICROSECONDS_PER_SECOND));
        }
    }

    /** One connection from start to end. Returns why it ended when there is nothing more to listen to. */
    private function listen(): string
    {
        $socket = WebSocketClient::connect($this->url);

        try {
            $sessionId = null;
            $keepaliveSeconds = self::DEFAULT_KEEPALIVE_SECONDS;
            $lastMessageAt = microtime(true);
            // the channels this connection listens to, with the ids of their subscriptions
            $subscribed = [];
            // those still to subscribe to, and to unsubscribe from
            $pending = [];
            $stale = [];
            // when each channel Twitch refused may be asked for again
            $retryAt = [];
            $nextSync = 0.0;
            $seen = [];

            while (true) {
                $message = $socket->receive(min(($this->betweenEvents)(), self::MAX_WAIT_SECONDS));

                if ($message !== null) {
                    $lastMessageAt = microtime(true);
                    $frame = json_decode($message, true);
                    $type = is_array($frame) ? ($frame['metadata']['message_type'] ?? null) : null;
                    $payload = is_array($frame['payload'] ?? null) ? $frame['payload'] : [];

                    if ($type === 'session_welcome') {
                        $sessionId = $this->sessionId($payload);
                        $keepaliveSeconds = $this->keepalive($payload);
                        Log::line('Connected to Twitch');
                    } elseif ($type === 'session_reconnect') {
                        // Twitch moves the bot to another server and keeps the subscriptions, so the new connection
                        // is opened first and the old one closed once it has said hello: no message is missed
                        $next = $this->openReconnect($payload);
                        $socket->close();
                        $socket = $next['socket'];
                        $sessionId = $next['sessionId'];
                        $keepaliveSeconds = $next['keepaliveSeconds'];
                        $lastMessageAt = microtime(true);
                        Log::line('Moved to another Twitch server');
                    } elseif ($type === 'revocation') {
                        $this->forgetRevoked($payload, $subscribed);
                    } elseif ($type === 'notification' && !$this->isRepeat($frame, $seen)) {
                        $this->passOn($payload);
                    }
                }

                if ($sessionId !== null) {
                    if (microtime(true) >= $nextSync) {
                        $wanted = array_map('strval', ($this->wantedChannels)());

                        // the subscriptions of a connection end with it, so with nothing to listen to it is closed
                        if ($wanted === []) {
                            return 'no channel to listen to any more';
                        }

                        // ids of digits become numbers as keys, so they are made text again
                        $listening = array_map('strval', array_keys($subscribed));
                        $pending = array_values(array_filter(
                            array_diff($wanted, $listening),
                            fn(string $channelId): bool => ($retryAt[$channelId] ?? 0.0) <= microtime(true),
                        ));
                        $stale = array_values(array_diff($listening, $wanted));
                        $nextSync = microtime(true) + self::SYNC_SECONDS;
                    }

                    foreach (array_splice($stale, 0, self::REQUESTS_PER_ROUND) as $channelId) {
                        $this->stopListening($channelId, $subscribed);
                    }

                    foreach (array_splice($pending, 0, self::REQUESTS_PER_ROUND) as $channelId) {
                        $this->listenTo($sessionId, $channelId, $subscribed, $retryAt);
                    }
                }

                // Twitch sends a keepalive when there is nothing else, so silence means the connection hangs
                if (microtime(true) - $lastMessageAt > $keepaliveSeconds + self::KEEPALIVE_MARGIN_SECONDS) {
                    throw new RuntimeException('Twitch stopped sending keepalive messages');
                }
            }
        } finally {
            $socket->close();
        }
    }

    /**
     * @param array<string, string> $subscribed
     * @param array<string, float> $retryAt
     */
    private function listenTo(string $sessionId, string $channelId, array &$subscribed, array &$retryAt): void
    {
        try {
            $subscribed[$channelId] = ($this->subscribe)($sessionId, $channelId);
        } catch (RuntimeException $error) {
            // usually the bot cannot read this chat yet, or the connection has as many chats as Twitch allows
            $retryAt[$channelId] = microtime(true) + self::REFUSED_RETRY_SECONDS;
            Log::line("Cannot listen to the chat of channel $channelId, trying again in " . self::REFUSED_RETRY_SECONDS . ' s: ' . $error->getMessage());

            return;
        }

        unset($retryAt[$channelId]);
        Log::line("Listening to the chat of channel $channelId");
    }

    /**
     * Stops listening to a channel the bot no longer moderates, such as one paused in the panel.
     *
     * @param array<string, string> $subscribed
     */
    private function stopListening(string $channelId, array &$subscribed): void
    {
        // Twitch may have taken the subscription back since the sync
        if (!isset($subscribed[$channelId])) {
            return;
        }

        try {
            ($this->unsubscribe)($subscribed[$channelId]);
        } catch (RuntimeException $error) {
            // tried again at the next sync; until then its messages still come, and are left alone, as the channel is not active
            Log::line("Cannot stop listening to the chat of channel $channelId: " . $error->getMessage());

            return;
        }

        unset($subscribed[$channelId]);
        Log::line("Stopped listening to the chat of channel $channelId");
    }

    /**
     * Opens the connection Twitch moves the bot to and waits for its hello.
     *
     * @return array{socket: WebSocketClient, sessionId: string, keepaliveSeconds: int}
     */
    private function openReconnect(array $payload): array
    {
        $url = $payload['session']['reconnect_url'] ?? null;

        if (!is_string($url)) {
            throw new RuntimeException('Twitch asked the bot to move without saying where');
        }

        if (!self::isTrustedReconnectUrl($url, $this->url)) {
            throw new RuntimeException('Twitch asked the bot to move to an address that is not Twitch\'s, so it connects anew instead');
        }

        $socket = WebSocketClient::connect($url);

        try {
            $hello = $socket->receive(self::WELCOME_TIMEOUT_SECONDS);
            $frame = $hello === null ? null : json_decode($hello, true);

            if (!is_array($frame) || ($frame['metadata']['message_type'] ?? null) !== 'session_welcome') {
                throw new RuntimeException('The server Twitch moved the bot to did not say hello');
            }

            $payload = is_array($frame['payload'] ?? null) ? $frame['payload'] : [];

            return ['socket' => $socket, 'sessionId' => $this->sessionId($payload), 'keepaliveSeconds' => $this->keepalive($payload)];
        } catch (RuntimeException $error) {
            $socket->close();

            throw $error;
        }
    }

    private function sessionId(array $payload): string
    {
        $id = $payload['session']['id'] ?? null;

        if (!is_string($id) || $id === '') {
            throw new RuntimeException('Twitch said hello without a session');
        }

        return $id;
    }

    private function keepalive(array $payload): int
    {
        $seconds = $payload['session']['keepalive_timeout_seconds'] ?? null;

        return is_int($seconds) && $seconds > 0 ? $seconds : self::DEFAULT_KEEPALIVE_SECONDS;
    }

    /** Twitch took a subscription back, such as when the channel's owner took the bot's access back; it is made again at the next sync. */
    private function forgetRevoked(array $payload, array &$subscribed): void
    {
        $channelId = $payload['subscription']['condition']['broadcaster_user_id'] ?? null;
        $reason = $payload['subscription']['status'] ?? 'unknown';

        if (is_string($channelId)) {
            unset($subscribed[$channelId]);
            Log::line("Twitch took back the subscription to channel $channelId: " . (is_string($reason) ? $reason : 'unknown'));
        }
    }

    /** Whether Twitch sent this notification before; the ids of the latest ones are remembered. */
    private function isRepeat(array $frame, array &$seen): bool
    {
        $id = $frame['metadata']['message_id'] ?? null;

        if (!is_string($id)) {
            return false;
        }

        if (isset($seen[$id])) {
            return true;
        }

        $seen[$id] = true;

        if (count($seen) > self::REMEMBERED_NOTIFICATIONS) {
            array_shift($seen);
        }

        return false;
    }

    private function passOn(array $payload): void
    {
        $event = $payload['event'] ?? null;

        if (($payload['subscription']['type'] ?? null) === self::TYPE_CHAT_MESSAGE && is_array($event)) {
            ($this->onMessage)($event);
        }
    }
}
