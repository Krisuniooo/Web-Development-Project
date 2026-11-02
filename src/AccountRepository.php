<?php

declare(strict_types=1);

namespace App;

use App\Model\Account;
use PDO;

final class AccountRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function find(int $id): ?Account
    {
        $statement = $this->pdo->prepare('SELECT id, platform, platform_user_id, display_name FROM accounts WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return new Account((int) $row['id'], $row['platform'], $row['platform_user_id'], $row['display_name']);
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
