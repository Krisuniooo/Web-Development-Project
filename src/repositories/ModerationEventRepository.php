<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\ModerationEvent;
use DateTimeImmutable;
use PDO;

final class ModerationEventRepository
{
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

        return array_map(fn(array $row): ModerationEvent => new ModerationEvent(
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
        ), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Saves what the bot did about a message. */
    public function add(ModerationEvent $event): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO moderation_events (
                channel_id, platform_channel_id, channel_name, platform_message_id, author_platform_id, author_name,
                category, level, model, action, content, score
             ) VALUES (
                :channel_id, :platform_channel_id, :channel_name, :platform_message_id, :author_platform_id, :author_name,
                :category, :level, :model, :action, :content, :score
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
}
