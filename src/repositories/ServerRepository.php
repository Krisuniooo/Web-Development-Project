<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class ServerRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * The servers from the list that have the bot on them.
     *
     * @param list<string> $platformServerIds digits only, as they go into a Postgres array
     * @return list<string>
     */
    public function findActiveIds(string $platform, array $platformServerIds): array
    {
        $statement = $this->pdo->prepare(
            "SELECT platform_server_id FROM servers
             WHERE platform = :platform AND status = 'active' AND platform_server_id = ANY (CAST(:ids AS TEXT[]))",
        );
        $statement->execute([
            'platform' => $platform,
            'ids' => '{' . implode(',', $platformServerIds) . '}',
        ]);

        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Adds the server as active, or brings it back and updates its name if it was there before. */
    public function saveActive(string $platform, string $platformServerId, string $name): void
    {
        // a row that would not change is skipped, so updated_at keeps the time of the last real change
        $statement = $this->pdo->prepare(
            "INSERT INTO servers (platform, platform_server_id, name)
             VALUES (:platform, :platform_server_id, :name)
             ON CONFLICT (platform, platform_server_id) DO UPDATE SET name = EXCLUDED.name, status = 'active'
             WHERE servers.name <> EXCLUDED.name OR servers.status <> 'active'",
        );
        $statement->execute([
            'platform' => $platform,
            'platform_server_id' => $platformServerId,
            'name' => $name,
        ]);
    }

    public function markRemoved(string $platform, string $platformServerId): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE servers SET status = 'removed'
             WHERE platform = :platform AND platform_server_id = :platform_server_id AND status = 'active'",
        );
        $statement->execute([
            'platform' => $platform,
            'platform_server_id' => $platformServerId,
        ]);
    }

    /**
     * Marks every active server that is not on the list as removed.
     *
     * @param list<string> $platformServerIds digits only, as they go into a Postgres array
     */
    public function markRemovedExcept(string $platform, array $platformServerIds): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE servers SET status = 'removed'
             WHERE platform = :platform AND status = 'active' AND platform_server_id <> ALL (CAST(:kept AS TEXT[]))",
        );
        $statement->execute([
            'platform' => $platform,
            'kept' => '{' . implode(',', $platformServerIds) . '}',
        ]);
    }

    /**
     * Deletes the servers the bot left more than $days ago, and with them their channels, rules and events.
     * Returns how many servers it deleted.
     */
    public function deleteRemovedFor(int $days): int
    {
        // updated_at of a removed server is the time the bot left, since nothing changes the row after that
        $statement = $this->pdo->prepare(
            "DELETE FROM servers WHERE status = 'removed' AND updated_at < now() - make_interval(days => :days)",
        );
        $statement->bindValue('days', $days, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount();
    }
}
