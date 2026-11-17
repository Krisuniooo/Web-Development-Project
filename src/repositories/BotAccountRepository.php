<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/** The account a platform's bot acts as, with the tokens as they are stored: sealed, see App\Secrets. */
final class BotAccountRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array{userId: string, name: string, refreshToken: string, accessToken: string, expiresAt: int}|null */
    public function find(string $platform): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT platform_user_id, name, refresh_token, access_token, CAST(EXTRACT(EPOCH FROM access_expires_at) AS BIGINT) AS expires_at
             FROM bot_accounts WHERE platform = :platform',
        );
        $statement->execute(['platform' => $platform]);
        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        return [
            'userId' => $row['platform_user_id'],
            'name' => $row['name'],
            'refreshToken' => $row['refresh_token'],
            'accessToken' => $row['access_token'],
            'expiresAt' => (int) $row['expires_at'],
        ];
    }

    /** The name of the account, for the panel, which has no use for the tokens. */
    public function findName(string $platform): ?string
    {
        $statement = $this->pdo->prepare('SELECT name FROM bot_accounts WHERE platform = :platform');
        $statement->execute(['platform' => $platform]);
        $name = $statement->fetchColumn();

        return is_string($name) ? $name : null;
    }

    /** Saves the account, replacing the one the platform had before. The tokens are given sealed. */
    public function save(string $platform, string $platformUserId, string $name, string $refreshToken, string $accessToken, int $expiresAt): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO bot_accounts (platform, platform_user_id, name, refresh_token, access_token, access_expires_at)
             VALUES (:platform, :platform_user_id, :name, :refresh_token, :access_token, to_timestamp(:expires_at))
             ON CONFLICT (platform) DO UPDATE SET platform_user_id = EXCLUDED.platform_user_id, name = EXCLUDED.name,
                 refresh_token = EXCLUDED.refresh_token, access_token = EXCLUDED.access_token,
                 access_expires_at = EXCLUDED.access_expires_at',
        );
        $statement->bindValue('platform', $platform);
        $statement->bindValue('platform_user_id', $platformUserId);
        $statement->bindValue('name', $name);
        $statement->bindValue('refresh_token', $refreshToken);
        $statement->bindValue('access_token', $accessToken);
        $statement->bindValue('expires_at', $expiresAt, PDO::PARAM_INT);
        $statement->execute();
    }

    /** New tokens for the same account, after a refresh. Sealed too. */
    public function saveTokens(string $platform, string $refreshToken, string $accessToken, int $expiresAt): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE bot_accounts SET refresh_token = :refresh_token, access_token = :access_token,
                 access_expires_at = to_timestamp(:expires_at)
             WHERE platform = :platform',
        );
        $statement->bindValue('platform', $platform);
        $statement->bindValue('refresh_token', $refreshToken);
        $statement->bindValue('access_token', $accessToken);
        $statement->bindValue('expires_at', $expiresAt, PDO::PARAM_INT);
        $statement->execute();
    }
}
