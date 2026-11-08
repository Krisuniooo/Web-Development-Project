<?php

declare(strict_types=1);

namespace App;

use App\Model\Channel;
use App\Model\ChannelRule;
use DateTimeImmutable;
use PDO;
use Throwable;

final class ChannelRepository
{
    // the places for channels on one server, as the CHECK on channels.slot in schema.sql allows
    public const SLOTS = [1, 2];

    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<Channel> the server's channels, in the order of their slots */
    public function findForServer(string $platform, string $platformServerId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT channels.* FROM channels JOIN servers ON servers.id = channels.server_id
             WHERE servers.platform = :platform AND servers.platform_server_id = :platform_server_id
             ORDER BY channels.slot',
        );
        $statement->execute([
            'platform' => $platform,
            'platform_server_id' => $platformServerId,
        ]);

        return array_map(fn(array $row): Channel => new Channel(
            (int) $row['id'],
            (int) $row['server_id'],
            (int) $row['slot'],
            $row['platform_channel_id'],
            $row['name'],
            $row['model'],
            $row['language'],
            $row['status'],
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['updated_at']),
        ), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function setLanguage(int $channelId, string $language): void
    {
        $statement = $this->pdo->prepare('UPDATE channels SET language = :language WHERE id = :id');
        $statement->execute([
            'language' => $language,
            'id' => $channelId,
        ]);
    }

    /** @return array<string, ChannelRule> the channel's rules by category */
    public function findRules(int $channelId): array
    {
        $statement = $this->pdo->prepare('SELECT category, level, action, updated_at FROM channel_rules WHERE channel_id = :channel_id');
        $statement->execute(['channel_id' => $channelId]);
        $rules = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rules[$row['category']] = new ChannelRule(
                $channelId,
                $row['category'],
                $row['level'],
                $row['action'],
                new DateTimeImmutable($row['updated_at']),
            );
        }

        return $rules;
    }

    /**
     * Saves the given rules together or not at all, adding the ones a channel does not have yet.
     *
     * @param list<ChannelRule> $rules
     */
    public function saveRules(array $rules): void
    {
        $this->pdo->beginTransaction();

        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO channel_rules (channel_id, category, level, action) VALUES (:channel_id, :category, :level, :action)
                 ON CONFLICT (channel_id, category) DO UPDATE SET level = EXCLUDED.level, action = EXCLUDED.action',
            );

            foreach ($rules as $rule) {
                $statement->execute([
                    'channel_id' => $rule->getChannelId(),
                    'category' => $rule->getCategory(),
                    'level' => $rule->getLevel(),
                    'action' => $rule->getAction(),
                ]);
            }

            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $error;
        }
    }

    /**
     * Points a slot at another channel of the same server; the language, model, rules and status stay.
     * The database turns it down when the other channel is already connected.
     */
    public function setPlatformChannel(int $channelId, string $platformChannelId, string $name): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE channels SET platform_channel_id = :platform_channel_id, name = :name WHERE id = :id',
        );
        $statement->execute([
            'platform_channel_id' => $platformChannelId,
            'name' => $name,
            'id' => $channelId,
        ]);
    }

    /**
     * What the bot needs to check a message from this channel and act on it, or null when it does not moderate it:
     * the channel is not saved for this server, or it is paused.
     *
     * @return array{channelId: int, name: string, model: string, language: string, rules: array<string, array{level: string, action: string}>}|null
     *     rules by category
     */
    public function moderationSettings(string $platform, string $platformServerId, string $platformChannelId): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT channels.id, channels.name, channels.model, channels.language,
                    channel_rules.category, channel_rules.level, channel_rules.action
             FROM channels JOIN servers ON servers.id = channels.server_id
             LEFT JOIN channel_rules ON channel_rules.channel_id = channels.id
             WHERE servers.platform = :platform AND servers.platform_server_id = :platform_server_id
               AND channels.platform_channel_id = :platform_channel_id AND channels.status = 'active'",
        );
        $statement->execute([
            'platform' => $platform,
            'platform_server_id' => $platformServerId,
            'platform_channel_id' => $platformChannelId,
        ]);
        // one row for each rule of the channel
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            return null;
        }

        $rules = [];

        foreach ($rows as $row) {
            // a channel without rules still comes back once, with no category
            if ($row['category'] !== null) {
                $rules[$row['category']] = ['level' => $row['level'], 'action' => $row['action']];
            }
        }

        return [
            'channelId' => (int) $rows[0]['id'],
            'name' => $rows[0]['name'],
            'model' => $rows[0]['model'],
            'language' => $rows[0]['language'],
            'rules' => $rules,
        ];
    }

    /** The language of the server's first channel, or null while the server has no channel. */
    public function firstLanguage(string $platform, string $platformServerId): ?string
    {
        $statement = $this->pdo->prepare(
            'SELECT channels.language FROM channels JOIN servers ON servers.id = channels.server_id
             WHERE servers.platform = :platform AND servers.platform_server_id = :platform_server_id
             ORDER BY channels.slot LIMIT 1',
        );
        $statement->execute([
            'platform' => $platform,
            'platform_server_id' => $platformServerId,
        ]);
        $language = $statement->fetchColumn();

        return is_string($language) ? $language : null;
    }

    /**
     * Adds the first channel of a server with its rules, unless the server already has a channel.
     * Returns whether it added one.
     *
     * @param array<string, array{level: string, action: string}> $rules by category
     */
    public function createFirst(
        string $platform,
        string $platformServerId,
        string $platformChannelId,
        string $name,
        string $model,
        string $language,
        array $rules,
    ): bool {
        return $this->insertWithRules(
            'INSERT INTO channels (server_id, slot, platform_channel_id, name, model, language)
             SELECT id, 1, :platform_channel_id, :name, :model, :language FROM servers
             WHERE platform = :platform AND platform_server_id = :platform_server_id
               AND NOT EXISTS (SELECT 1 FROM channels WHERE channels.server_id = servers.id)
             RETURNING id',
            [
                'platform_channel_id' => $platformChannelId,
                'name' => $name,
                'model' => $model,
                'language' => $language,
                'platform' => $platform,
                'platform_server_id' => $platformServerId,
            ],
            $rules,
        );
    }

    /**
     * Adds a channel of a server with its rules in the given slot. Returns whether it added one.
     * The database turns it down when the slot or the channel is already taken.
     *
     * @param array<string, array{level: string, action: string}> $rules by category
     */
    public function add(
        string $platform,
        string $platformServerId,
        int $slot,
        string $platformChannelId,
        string $name,
        string $model,
        string $language,
        array $rules,
    ): bool {
        return $this->insertWithRules(
            'INSERT INTO channels (server_id, slot, platform_channel_id, name, model, language)
             SELECT id, :slot, :platform_channel_id, :name, :model, :language FROM servers
             WHERE platform = :platform AND platform_server_id = :platform_server_id
             RETURNING id',
            [
                'slot' => $slot,
                'platform_channel_id' => $platformChannelId,
                'name' => $name,
                'model' => $model,
                'language' => $language,
                'platform' => $platform,
                'platform_server_id' => $platformServerId,
            ],
            $rules,
        );
    }

    /**
     * Runs an INSERT of one channel that returns its id, then adds the channel's rules.
     * Returns whether the INSERT added a channel.
     *
     * @param array<string, array{level: string, action: string}> $rules by category
     */
    private function insertWithRules(string $insert, array $parameters, array $rules): bool
    {
        // the channel and its rules are saved together or not at all
        $this->pdo->beginTransaction();

        try {
            $statement = $this->pdo->prepare($insert);
            $statement->execute($parameters);
            $channelId = $statement->fetchColumn();

            if ($channelId !== false) {
                $rule = $this->pdo->prepare(
                    'INSERT INTO channel_rules (channel_id, category, level, action) VALUES (:channel_id, :category, :level, :action)',
                );

                foreach ($rules as $category => $settings) {
                    $rule->execute([
                        'channel_id' => $channelId,
                        'category' => $category,
                        'level' => $settings['level'],
                        'action' => $settings['action'],
                    ]);
                }
            }

            $this->pdo->commit();

            return $channelId !== false;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $error;
        }
    }
}
