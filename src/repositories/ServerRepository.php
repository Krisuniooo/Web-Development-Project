<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class ServerRepository
{
    // how long the channels, rules and events of a server are kept after the bot left it, in case it comes back;
    // bin/cleanup.php deletes them after that
    public const KEEP_REMOVED_DAYS = 30;

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
     * Writes on which of the platform's active servers the bot is a moderator, for the panel. Only the rows that change,
     * so updated_at keeps the time of the last real change.
     *
     * @param list<string> $moderatedIds digits only, as they go into a Postgres array
     */
    public function saveBotModerator(string $platform, array $moderatedIds): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE servers SET bot_is_moderator = checked.is_moderator
             FROM (
                 SELECT id, platform_server_id = ANY (CAST(:ids AS TEXT[])) AS is_moderator
                 FROM servers WHERE platform = :platform AND status = 'active'
             ) AS checked
             WHERE servers.id = checked.id AND servers.bot_is_moderator IS DISTINCT FROM checked.is_moderator",
        );
        $statement->execute([
            'platform' => $platform,
            'ids' => '{' . implode(',', $moderatedIds) . '}',
        ]);
    }

    /** Whether the bot is a moderator of the server; null while the bot has not said, and on platforms where it never does. */
    public function findBotIsModerator(string $platform, string $platformServerId): ?bool
    {
        $statement = $this->pdo->prepare(
            'SELECT bot_is_moderator FROM servers WHERE platform = :platform AND platform_server_id = :platform_server_id',
        );
        $statement->execute([
            'platform' => $platform,
            'platform_server_id' => $platformServerId,
        ]);
        // the whole row, since a column that is false could not be told from no row at all
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row['bot_is_moderator'];
    }

    /**
     * Whom the bot leaves alone on the server, or null when the server is not saved.
     *
     * @return array{admins: bool, roleIds: list<string>}|null
     *     admins: the owner and administrators; roleIds: everyone with one of these roles
     */
    public function findExemptions(string $platform, string $platformServerId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT servers.admins_exempt, server_exempt_roles.platform_role_id
             FROM servers LEFT JOIN server_exempt_roles ON server_exempt_roles.server_id = servers.id
             WHERE servers.platform = :platform AND servers.platform_server_id = :platform_server_id',
        );
        $statement->execute([
            'platform' => $platform,
            'platform_server_id' => $platformServerId,
        ]);
        // one row for each exempt role; a server without any still comes back once, with no role
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            return null;
        }

        return [
            'admins' => $rows[0]['admins_exempt'],
            'roleIds' => array_values(array_filter(array_column($rows, 'platform_role_id'), 'is_string')),
        ];
    }

    /** Whether the bot leaves the owner and administrators of the server alone. */
    public function setAdminsExempt(string $platform, string $platformServerId, bool $isExempt): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE servers SET admins_exempt = :is_exempt
             WHERE platform = :platform AND platform_server_id = :platform_server_id',
        );
        $statement->bindValue('is_exempt', $isExempt, PDO::PARAM_BOOL);
        $statement->bindValue('platform', $platform);
        $statement->bindValue('platform_server_id', $platformServerId);
        $statement->execute();
    }

    /** From now on the bot leaves everyone with the role alone; a role that is exempt already stays so. */
    public function addExemptRole(string $platform, string $platformServerId, string $platformRoleId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO server_exempt_roles (server_id, platform_role_id)
             SELECT id, :platform_role_id FROM servers
             WHERE platform = :platform AND platform_server_id = :platform_server_id
             ON CONFLICT DO NOTHING',
        );
        $statement->execute([
            'platform_role_id' => $platformRoleId,
            'platform' => $platform,
            'platform_server_id' => $platformServerId,
        ]);
    }

    public function removeExemptRole(string $platform, string $platformServerId, string $platformRoleId): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM server_exempt_roles USING servers
             WHERE servers.id = server_exempt_roles.server_id
               AND servers.platform = :platform AND servers.platform_server_id = :platform_server_id
               AND server_exempt_roles.platform_role_id = :platform_role_id',
        );
        $statement->execute([
            'platform' => $platform,
            'platform_server_id' => $platformServerId,
            'platform_role_id' => $platformRoleId,
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
