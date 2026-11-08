<?php

declare(strict_types=1);

namespace App;

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
     *
     * @return list<ModerationEvent>
     */
    public function findForServer(string $platform, string $platformServerId, ?int $beforeId, int $limit): array
    {
        $statement = $this->pdo->prepare(
            'SELECT moderation_events.* FROM moderation_events
             JOIN channels ON channels.id = moderation_events.channel_id
             JOIN servers ON servers.id = channels.server_id
             WHERE servers.platform = :platform AND servers.platform_server_id = :platform_server_id'
            . ($beforeId === null ? '' : ' AND moderation_events.id < :before_id')
            . ' ORDER BY moderation_events.id DESC LIMIT :limit',
        );
        $statement->bindValue('platform', $platform);
        $statement->bindValue('platform_server_id', $platformServerId);
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);

        if ($beforeId !== null) {
            $statement->bindValue('before_id', $beforeId, PDO::PARAM_INT);
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
}
