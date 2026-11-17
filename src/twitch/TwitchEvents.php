<?php

declare(strict_types=1);

namespace App\Twitch;

use App\Database;
use App\Log;
use App\Moderation\ModerationQueue;
use App\Repository\ChannelRepository;
use App\Repository\ServerRepository;
use App\Repository\ServiceStatusRepository;
use Closure;
use PDO;
use PDOException;
use RuntimeException;

/**
 * What the bot does with what EventSub tells it: which channels to listen to, and what to do with a chat message.
 * A message from a channel the bot moderates goes to the queue for the model, with the settings its channel has now.
 * It also tells the panel the bot works, which it does only while the bot's account can be used,
 * and on which channels the bot is a moderator.
 */
final class TwitchEvents
{
    private const PLATFORM = 'twitch';
    // how often Twitch is asked which channels the bot moderates: as often as the bot reads the channels to listen to
    private const MODERATED_CHECK_SECONDS = 60;

    private ?PDO $pdo = null;
    private float $reportedAt = 0.0;
    private bool $hasSaidWaiting = false;
    // Twitch's last answer about the channels the bot moderates, null when it gave none
    private ?array $moderated = null;
    private float $moderatedCheckedAt = 0.0;
    private bool $hasSaidNoModeratedList = false;
    // the connected channels the log last said the bot does not moderate
    private array $saidNotModerated = [];

    public function __construct(
        private Database $database,
        private ModerationQueue $queue,
        private BotToken $token,
        private TwitchBotClient $twitch,
    ) {
    }

    /**
     * The channels to listen to: those connected and active in the panel where the bot is a moderator, since only there
     * can it delete a message; judging the others would only spend the model. None until someone has authorized
     * the bot's account, since without it there is nothing to listen as.
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
        $active = $this->withDatabase(fn(PDO $pdo): array => (new ChannelRepository($pdo))->activeChannelIds(self::PLATFORM));
        $moderated = $this->moderatedChannels();

        // without Twitch's answer every connected channel is listened to, as before the bot could ask
        if ($moderated === null) {
            return $active;
        }

        $this->sayNotModerated(array_values(array_diff($active, $moderated)));

        return array_values(array_intersect($active, $moderated));
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
     * The channels the bot is a moderator of, asked of Twitch at most once a minute and written down for the panel,
     * so it can tell the owner of a channel the bot lost. Null when Twitch does not say, such as before the bot's
     * account allowed reading the list.
     *
     * @return list<string>|null
     */
    private function moderatedChannels(): ?array
    {
        if (microtime(true) - $this->moderatedCheckedAt < self::MODERATED_CHECK_SECONDS) {
            return $this->moderated;
        }

        $this->moderatedCheckedAt = microtime(true);

        try {
            $this->moderated = $this->twitch->moderatedChannelIds();
        } catch (RuntimeException $error) {
            $this->moderated = null;

            // said once until Twitch answers again, since the bot asks every minute
            if (!$this->hasSaidNoModeratedList) {
                Log::line('Cannot read the channels the bot moderates, so it listens to all connected ones: ' . $error->getMessage()
                    . (TwitchClient::isUnauthorized($error) ? '. Authorize the bot account again at /auth/twitch/bot' : ''));
                $this->hasSaidNoModeratedList = true;
            }

            return null;
        }

        $this->hasSaidNoModeratedList = false;
        $this->withDatabase(fn(PDO $pdo) => (new ServerRepository($pdo))->saveBotModerator(self::PLATFORM, $this->moderated));

        return $this->moderated;
    }

    /**
     * Tells the log which connected channels the bot does not listen to, for not being their moderator, when that changes.
     *
     * @param list<string> $channelIds
     */
    private function sayNotModerated(array $channelIds): void
    {
        if ($channelIds === $this->saidNotModerated) {
            return;
        }

        if ($channelIds !== []) {
            Log::line('Not a moderator of channel ' . implode(', ', $channelIds) . ', so the bot does not listen there until it is made one again');
        }

        $this->saidNotModerated = $channelIds;
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
