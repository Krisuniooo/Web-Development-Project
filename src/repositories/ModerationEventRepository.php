<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\ModerationEvent;
use DateTimeImmutable;
use PDO;

final class ModerationEventRepository
{
    // how long the text and the author of a moderated message are kept; bin/cleanup.php removes them after that,
    // and the rest of the event stays for statistics
    public const KEEP_CONTENT_DAYS = 30;

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * The events from the channels of one server, newest first; with $beforeId only those older than that event.
     * A channel, a category or an action narrows the list to the events with it.
     *
     * @return list<ModerationEvent>
     */
    public function findForServer(
        string $platform,
        string $platformServerId,
        ?int $beforeId,
        int $limit,
        ?string $platformChannelId = null,
        ?string $category = null,
        ?string $action = null,
    ): array {
        $conditions = ['servers.platform = :platform', 'servers.platform_server_id = :platform_server_id'];
        $parameters = ['platform' => $platform, 'platform_server_id' => $platformServerId, 'limit' => $limit];

        // each of these joins the query only when it was asked for
        $optional = [
            'before_id' => ['moderation_events.id < :before_id', $beforeId],
            'platform_channel_id' => ['moderation_events.platform_channel_id = :platform_channel_id', $platformChannelId],
            'category' => ['moderation_events.category = CAST(:category AS moderation_category)', $category],
            'action' => ['moderation_events.action = CAST(:action AS moderation_action)', $action],
        ];

        foreach ($optional as $name => [$condition, $value]) {
            if ($value !== null) {
                $conditions[] = $condition;
                $parameters[$name] = $value;
            }
        }

        $statement = $this->pdo->prepare(
            'SELECT moderation_events.* FROM moderation_events
             JOIN channels ON channels.id = moderation_events.channel_id
             JOIN servers ON servers.id = channels.server_id
             WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY moderation_events.id DESC LIMIT :limit',
        );

        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $statement->execute();

        return array_map($this->toEvent(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * One event with the server of its channel, so the caller can tell a missing event from one of another server.
     *
     * @return array{event: ModerationEvent, platform: string, platformServerId: string}|null
     */
    public function findWithServer(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT moderation_events.*, servers.platform, servers.platform_server_id FROM moderation_events
             JOIN channels ON channels.id = moderation_events.channel_id
             JOIN servers ON servers.id = channels.server_id
             WHERE moderation_events.id = :id',
        );
        $statement->bindValue('id', $id, PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : [
            'event' => $this->toEvent($row),
            'platform' => $row['platform'],
            'platformServerId' => $row['platform_server_id'],
        ];
    }

    /**
     * What the bot did on one server in the last $days days: the actions done and failed,
     * the done ones by channel, and every broken rule by category, whether its action worked or not.
     *
     * @return array{acted: int, failed: int, actedByChannel: array<int, int>, byCategory: array<string, int>}
     */
    public function summarize(string $platform, string $platformServerId, int $days): array
    {
        $statement = $this->pdo->prepare(
            'SELECT moderation_events.channel_id, CAST(moderation_events.category AS TEXT) AS category,
                    moderation_events.failure IS NULL AS is_done, count(*) AS events
             FROM moderation_events
             JOIN channels ON channels.id = moderation_events.channel_id
             JOIN servers ON servers.id = channels.server_id
             WHERE servers.platform = :platform AND servers.platform_server_id = :platform_server_id
               AND moderation_events.created_at > now() - make_interval(days => :days)
             GROUP BY 1, 2, 3',
        );
        $statement->bindValue('platform', $platform);
        $statement->bindValue('platform_server_id', $platformServerId);
        $statement->bindValue('days', $days, PDO::PARAM_INT);
        $statement->execute();

        $summary = ['acted' => 0, 'failed' => 0, 'actedByChannel' => [], 'byCategory' => []];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $events = (int) $row['events'];
            $summary['byCategory'][$row['category']] = ($summary['byCategory'][$row['category']] ?? 0) + $events;

            if ($row['is_done']) {
                $summary['acted'] += $events;
                $channelId = (int) $row['channel_id'];
                $summary['actedByChannel'][$channelId] = ($summary['actedByChannel'][$channelId] ?? 0) + $events;
            } else {
                $summary['failed'] += $events;
            }
        }

        return $summary;
    }

    /**
     * The authors who broke the rules most often on one server in the last $days days, with the name they used last
     * and the rule they broke most. An author removed by bin/cleanup.php after KEEP_CONTENT_DAYS no longer counts.
     * With $authorPlatformId only that author, as a list of one, or of none when they broke no rule then.
     *
     * @return list<array{id: string, name: string, events: int, category: string}>
     */
    public function topAuthors(
        string $platform,
        string $platformServerId,
        int $days,
        int $limit,
        ?string $authorPlatformId = null,
    ): array {
        $statement = $this->pdo->prepare(
            'SELECT moderation_events.author_platform_id AS id,
                    (array_agg(moderation_events.author_name ORDER BY moderation_events.id DESC))[1] AS name,
                    count(*) AS events,
                    CAST(mode() WITHIN GROUP (ORDER BY moderation_events.category) AS TEXT) AS category
             FROM moderation_events
             JOIN channels ON channels.id = moderation_events.channel_id
             JOIN servers ON servers.id = channels.server_id
             WHERE servers.platform = :platform AND servers.platform_server_id = :platform_server_id
               AND moderation_events.author_platform_id IS NOT NULL
               AND moderation_events.created_at > now() - make_interval(days => :days)'
            . ($authorPlatformId === null ? '' : ' AND moderation_events.author_platform_id = :author_platform_id') . '
             GROUP BY moderation_events.author_platform_id
             ORDER BY events DESC, max(moderation_events.id) DESC
             LIMIT :limit',
        );
        $statement->bindValue('platform', $platform);
        $statement->bindValue('platform_server_id', $platformServerId);

        if ($authorPlatformId !== null) {
            $statement->bindValue('author_platform_id', $authorPlatformId);
        }

        $statement->bindValue('days', $days, PDO::PARAM_INT);
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map(fn(array $row): array => [
            'id' => $row['id'],
            'name' => $row['name'],
            'events' => (int) $row['events'],
            'category' => $row['category'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Saves what the bot did about a message, or tried to do. */
    public function add(ModerationEvent $event): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO moderation_events (
                channel_id, platform_channel_id, channel_name, platform_message_id, author_platform_id, author_name,
                category, level, model, action, content, score, failure
             ) VALUES (
                :channel_id, :platform_channel_id, :channel_name, :platform_message_id, :author_platform_id, :author_name,
                :category, :level, :model, :action, :content, :score, :failure
             )',
        );
        $statement->execute([
            'channel_id' => $event->getChannelId(),
            'platform_channel_id' => $event->getPlatformChannelId(),
            'channel_name' => $event->getChannelName(),
            'platform_message_id' => $event->getPlatformMessageId(),
            'author_platform_id' => $event->getAuthorPlatformId(),
            'author_name' => $event->getAuthorName(),
            'category' => $event->getCategory(),
            'level' => $event->getLevel(),
            'model' => $event->getModel(),
            'action' => $event->getAction(),
            'content' => $event->getContent(),
            'score' => $event->getScore(),
            'failure' => $event->getFailure(),
        ]);
    }

    /**
     * Removes the text and the author from the events older than $days; the rest stays for statistics.
     * Returns how many events it changed, which is none when run again.
     */
    public function stripOlderThan(int $days): int
    {
        $statement = $this->pdo->prepare(
            'UPDATE moderation_events SET content = NULL, author_platform_id = NULL, author_name = NULL
             WHERE created_at < now() - make_interval(days => :days)
               AND (content IS NOT NULL OR author_platform_id IS NOT NULL OR author_name IS NOT NULL)',
        );
        $statement->bindValue('days', $days, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount();
    }

    private function toEvent(array $row): ModerationEvent
    {
        return new ModerationEvent(
            (int) $row['id'],
            (int) $row['channel_id'],
            $row['platform_channel_id'],
            $row['channel_name'],
            $row['platform_message_id'],
            $row['author_platform_id'],
            $row['author_name'],
            $row['category'],
            $row['level'],
            $row['model'],
            $row['action'],
            $row['content'],
            $row['score'] === null ? null : (float) $row['score'],
            new DateTimeImmutable($row['created_at']),
            $row['failure'],
        );
    }
}
