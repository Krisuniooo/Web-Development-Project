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

    /**
     * Who the account is, for the panel, which has no use for the tokens.
     *
     * @return array{userId: string, name: string}|null
     */
    public function findIdentity(string $platform): ?array
    {
        $statement = $this->pdo->prepare('SELECT platform_user_id, name FROM bot_accounts WHERE platform = :platform');
        $statement->execute(['platform' => $platform]);
        $row = $statement->fetch();

        return $row === false ? null : ['userId' => $row['platform_user_id'], 'name' => $row['name']];
    }

    public function findName(string $platform): ?string
    {
        return $this->findIdentity($platform)['name'] ?? null;
    }

    /**
     * Saves the platform's first account, or new tokens and the current name of the same account. Another account
     * never replaces it, even one that took over the name the bot had: that one would be made a moderator of every
     * channel connected after it. The tokens are given sealed.
     *
     * @return bool false when the platform already has another account, and nothing was saved
     */
    public function save(string $platform, string $platformUserId, string $name, string $refreshToken, string $accessToken, int $expiresAt): bool
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO bot_accounts (platform, platform_user_id, name, refresh_token, access_token, access_expires_at)
             VALUES (:platform, :platform_user_id, :name, :refresh_token, :access_token, to_timestamp(:expires_at))
             ON CONFLICT (platform) DO UPDATE SET name = EXCLUDED.name,
                 refresh_token = EXCLUDED.refresh_token, access_token = EXCLUDED.access_token,
                 access_expires_at = EXCLUDED.access_expires_at
             WHERE bot_accounts.platform_user_id = EXCLUDED.platform_user_id',
        );
        $statement->bindValue('platform', $platform);
        $statement->bindValue('platform_user_id', $platformUserId);
        $statement->bindValue('name', $name);
        $statement->bindValue('refresh_token', $refreshToken);
        $statement->bindValue('access_token', $accessToken);
        $statement->bindValue('expires_at', $expiresAt, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount() === 1;
    }

    /**
     * New tokens for the same account, after a refresh. Sealed too. Only while it is still the platform's account,
     * so a refresh that ends after the account was replaced does not give the new one the old one's tokens.
     */
    public function saveTokens(string $platform, string $platformUserId, string $refreshToken, string $accessToken, int $expiresAt): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE bot_accounts SET refresh_token = :refresh_token, access_token = :access_token,
                 access_expires_at = to_timestamp(:expires_at)
             WHERE platform = :platform AND platform_user_id = :platform_user_id',
        );
        $statement->bindValue('platform', $platform);
        $statement->bindValue('platform_user_id', $platformUserId);
        $statement->bindValue('refresh_token', $refreshToken);
        $statement->bindValue('access_token', $accessToken);
        $statement->bindValue('expires_at', $expiresAt, PDO::PARAM_INT);
        $statement->execute();
    }
}
