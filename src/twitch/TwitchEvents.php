<?php

declare(strict_types=1);

namespace App\Twitch;

use App\Database;
use App\Log;
use App\Moderation\ModerationQueue;
use App\Repository\ChannelRepository;
use App\Repository\ServiceStatusRepository;
use Closure;
use PDO;
use PDOException;

/**
 * What the bot does with what EventSub tells it: which channels to listen to, and what to do with a chat message.
 * A message from a channel the bot moderates goes to the queue for the model, with the settings its channel has now.
 * It also tells the panel the bot works, which it does only while the bot's account can be used.
 */
final class TwitchEvents
{
    private const PLATFORM = 'twitch';

    private ?PDO $pdo = null;
    private float $reportedAt = 0.0;
    private bool $hasSaidWaiting = false;

    public function __construct(
        private Database $database,
        private ModerationQueue $queue,
        private BotToken $token,
        private TwitchBotClient $twitch,
    ) {
    }

    /**
     * The channels to listen to: those the bot moderates now. None until someone has authorized the bot's account,
     * since without it there is nothing to listen as.
     *
     * @return list<string>
     */
    public function wantedChannels(): array
    {
        if (!$this->token->isAuthorized()) {
            // said once, since the bot asks again every few seconds
            if (!$this->hasSaidWaiting) {
                Log::line('Waiting for the bot account: open /auth/twitch/bot while logged in to Twitch as the bot, and the bot starts by itself');
                $this->hasSaidWaiting = true;
            }

            return [];
        }

        $this->hasSaidWaiting = false;

        return $this->withDatabase(fn(PDO $pdo): array => (new ChannelRepository($pdo))->activeChannelIds(self::PLATFORM));
    }

    /**
     * @return string the id of the subscription
     * @throws \RuntimeException with Twitch's reason when it refuses
     */
    public function subscribe(string $sessionId, string $channelId): string
    {
        return $this->twitch->subscribeToChat($sessionId, $channelId);
    }

    /** @throws \RuntimeException with Twitch's reason when it refuses */
    public function unsubscribe(string $subscriptionId): void
    {
        $this->twitch->unsubscribe($subscriptionId);
    }

    /** Puts a message from a channel the bot moderates in the queue for the model; others are not its business. */
    public function handleMessage(array $event): void
    {
        $message = TwitchChat::messageFromEvent($event, $this->token->userId());

        if ($message === null) {
            return;
        }

        // a channel is its own server on Twitch, so both ids are the one of the broadcaster
        $settings = $this->withDatabase(
            fn(PDO $pdo): ?array => (new ChannelRepository($pdo))->moderationSettings(self::PLATFORM, $message['channelId'], $message['channelId']),
        );

        if ($settings === null) {
            return;
        }

        $this->queue->add($message, $settings);
    }

    /**
     * Tells the panel the bot works. The bot calls it between messages, so it writes only now and then;
     * when the bot stops or loses its token, the last report grows old and the panel shows it.
     *
     * @throws \RuntimeException when the bot's token cannot be used, which ends the connection like any other error
     */
    public function reportAlive(): void
    {
        if (microtime(true) - $this->reportedAt < ServiceStatusRepository::REPORT_SECONDS || !$this->token->isAuthorized()) {
            return;
        }

        // also keeps the token fresh while the bot has nothing to listen to
        $this->token->get();
        $this->withDatabase(fn(PDO $pdo) => (new ServiceStatusRepository($pdo))->report(ServiceStatusRepository::bot(self::PLATFORM)));
        $this->reportedAt = microtime(true);
    }

    /**
     * @template T
     * @param Closure(PDO): T $use
     * @return T
     */
    private function withDatabase(Closure $use): mixed
    {
        try {
            return $use($this->pdo ??= $this->database->connect());
        } catch (PDOException $error) {
            // a new connection at the next use, so a broken one is not kept
            $this->pdo = null;

            throw $error;
        }
    }
}
