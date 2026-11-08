<?php

declare(strict_types=1);

namespace App;

use App\Model\ModerationEvent;
use PDO;

final class ModerationEventRepository
{
    public function __construct(private PDO $pdo)
    {
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
