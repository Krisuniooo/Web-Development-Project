<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Turns Gateway events into changes in the database: which servers the bot is on,
 * and the first channel with the default settings when a server has none yet.
 * It also picks out the messages from the channels the bot moderates.
 */
final class DiscordEvents
{
    private const PLATFORM = 'discord';
    private const CHANNEL_TEXT = 0;
    private const CHANNEL_CATEGORY = 4;

    private ?ServerRepository $servers = null;
    private ?ChannelRepository $channels = null;
    private ?string $botUserId = null;
    private array $defaults;

    public function __construct(private Database $database)
    {
        $this->defaults = require dirname(__DIR__) . '/config/channel-defaults.php';
    }

    public function handle(string $type, array $data): void
    {
        if ($type === 'READY') {
            // a new database connection for every login: after any error the bot logs in again,
            // so a broken connection is never used twice
            $pdo = $this->database->connect();
            $this->servers = new ServerRepository($pdo);
            $this->channels = new ChannelRepository($pdo);
            $this->botUserId = is_string($data['user']['id'] ?? null) ? $data['user']['id'] : null;

            // READY lists every server the bot is on; one missing here was left while the bot was offline
            $kept = array_filter(
                array_column(is_array($data['guilds'] ?? null) ? $data['guilds'] : [], 'id'),
                self::isDiscordId(...),
            );
            $this->servers()->markRemovedExcept(self::PLATFORM, array_values($kept));
        } elseif ($type === 'GUILD_CREATE' || $type === 'GUILD_UPDATE') {
            $id = $data['id'] ?? null;
            $name = $data['name'] ?? null;

            if (self::isDiscordId($id) && is_string($name) && $name !== '' && !self::isOutage($data)) {
                $this->servers()->saveActive(self::PLATFORM, $id, $name);

                // GUILD_UPDATE has no channels in it
                if ($type === 'GUILD_CREATE') {
                    $this->createFirstChannel($id, $data);
                }
            }
        } elseif ($type === 'GUILD_DELETE') {
            $id = $data['id'] ?? null;

            if (self::isDiscordId($id) && !self::isOutage($data)) {
                $this->servers()->markRemoved(self::PLATFORM, $id);
            }
        } elseif ($type === 'MESSAGE_CREATE') {
            $this->checkMessage($data);
        }
    }

    /** For now only notes in the log that a message is one to moderate; checking it with the model comes next. */
    private function checkMessage(array $message): void
    {
        $text = $message['content'] ?? null;
        $serverId = $message['guild_id'] ?? null;
        $channelId = $message['channel_id'] ?? null;
        $messageId = $message['id'] ?? null;

        // other bots and webhooks are not people
        if (($message['author']['bot'] ?? false) === true || isset($message['webhook_id'])) {
            return;
        }

        // a message without text, such as a picture alone or "X joined the server", has nothing to check
        if (!is_string($text) || $text === '') {
            return;
        }

        if (!self::isDiscordId($serverId) || !self::isDiscordId($channelId) || !self::isDiscordId($messageId)) {
            return;
        }

        if (!$this->channels()->isModerated(self::PLATFORM, $serverId, $channelId)) {
            return;
        }

        // only the length, the text itself never goes to the log
        DiscordGateway::log(sprintf('Message %s in moderated channel %s, characters: %d', $messageId, $channelId, mb_strlen($text)));
    }

    /**
     * GUILD_CREATE comes after every login too, so this is not "the bot was just added" but "the server has no channel".
     * That covers a bot added while offline, and a server that gets the bot back keeps its old settings.
     */
    private function createFirstChannel(string $serverId, array $guild): void
    {
        $channel = $this->defaultChannel($guild);

        // without a channel the bot can see there is nothing to choose; the panel can set one later
        if ($channel === null) {
            return;
        }

        $this->channels()->createFirst(
            self::PLATFORM,
            $serverId,
            $channel['id'],
            $channel['name'],
            $this->defaults['model'],
            ($guild['preferred_locale'] ?? null) === 'pl' ? 'pl' : 'en',
            $this->defaults['rules'],
        );
    }

    /**
     * The system channel, where Discord greets new members, or else the first text channel in the order of the sidebar.
     * Only a channel the bot can see counts.
     *
     * @return array{id: string, name: string}|null
     */
    private function defaultChannel(array $guild): ?array
    {
        $channels = array_filter(is_array($guild['channels'] ?? null) ? $guild['channels'] : [], 'is_array');
        $categoryPositions = [];
        $textChannels = [];

        foreach ($channels as $channel) {
            if (($channel['type'] ?? null) === self::CHANNEL_CATEGORY) {
                $categoryPositions[$channel['id'] ?? ''] = (int) ($channel['position'] ?? 0);
            } elseif (($channel['type'] ?? null) === self::CHANNEL_TEXT && is_string($channel['id'] ?? null) && is_string($channel['name'] ?? null)) {
                $textChannels[] = $channel;
            }
        }

        $systemChannelId = $guild['system_channel_id'] ?? null;

        // the system channel first, then the sidebar: channels outside a category on top, then each category in turn
        $sortKey = fn(array $channel): array => [
            $channel['id'] === $systemChannelId ? 0 : 1,
            $categoryPositions[$channel['parent_id'] ?? ''] ?? -1,
            (int) ($channel['position'] ?? 0),
            (int) $channel['id'],
        ];
        usort($textChannels, fn(array $a, array $b): int => $sortKey($a) <=> $sortKey($b));

        $bot = $this->botMember($guild);

        foreach ($textChannels as $channel) {
            if ((DiscordPermissions::inChannel($guild, $bot, $channel) & DiscordPermissions::VIEW_CHANNEL) !== 0) {
                return ['id' => $channel['id'], 'name' => $channel['name']];
            }
        }

        return null;
    }

    /** The bot as a member of the server, with its roles. GUILD_CREATE always includes it. */
    private function botMember(array $guild): array
    {
        foreach (is_array($guild['members'] ?? null) ? $guild['members'] : [] as $member) {
            if (is_array($member) && $this->botUserId !== null && ($member['user']['id'] ?? null) === $this->botUserId) {
                return $member;
            }
        }

        // should it ever be missing, the bot is judged by @everyone alone
        return ['user' => ['id' => $this->botUserId], 'roles' => []];
    }

    private function servers(): ServerRepository
    {
        return $this->servers ?? throw new RuntimeException('A server event came before READY');
    }

    private function channels(): ChannelRepository
    {
        return $this->channels ?? throw new RuntimeException('A server event came before READY');
    }

    // the server is down at Discord for a while, but the bot is still on it
    private static function isOutage(array $data): bool
    {
        return ($data['unavailable'] ?? false) === true;
    }

    // ids of servers, channels and messages are all numbers of up to 20 digits
    private static function isDiscordId(mixed $id): bool
    {
        return is_string($id) && preg_match('/^\d{1,20}$/', $id) === 1;
    }
}
