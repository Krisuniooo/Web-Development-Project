<?php

declare(strict_types=1);

namespace App;

use PDO;

final class AccountRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** Adds the account, or updates its name if it logged in before, and returns its id. */
    public function saveFromLogin(string $platform, string $platformUserId, string $displayName): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO accounts (platform, platform_user_id, display_name)
             VALUES (:platform, :platform_user_id, :display_name)
             ON CONFLICT (platform, platform_user_id) DO UPDATE SET display_name = EXCLUDED.display_name
             RETURNING id',
        );
        $statement->execute([
            'platform' => $platform,
            'platform_user_id' => $platformUserId,
            'display_name' => $displayName,
        ]);

        return (int) $statement->fetchColumn();
    }
}
