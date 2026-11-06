<?php

declare(strict_types=1);

namespace App;

use PDO;
use Throwable;

final class ChannelRepository
{
    public function __construct(private PDO $pdo)
    {
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
        // the channel and its rules are saved together or not at all
        $this->pdo->beginTransaction();

        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO channels (server_id, slot, platform_channel_id, name, model, language)
                 SELECT id, 1, :platform_channel_id, :name, :model, :language FROM servers
                 WHERE platform = :platform AND platform_server_id = :platform_server_id
                   AND NOT EXISTS (SELECT 1 FROM channels WHERE channels.server_id = servers.id)
                 RETURNING id',
            );
            $statement->execute([
                'platform_channel_id' => $platformChannelId,
                'name' => $name,
                'model' => $model,
                'language' => $language,
                'platform' => $platform,
                'platform_server_id' => $platformServerId,
            ]);
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
