<?php

declare(strict_types=1);

namespace App\Twitch;

use App\Database;
use App\Repository\BotAccountRepository;
use App\Secrets;
use PDO;
use PDOException;
use RuntimeException;

/**
 * The access token of the Twitch account the bot acts as, always one that works. It lives in the database, sealed,
 * since it was given once in the browser and the bot must outlast restarts. A token that is about to expire is
 * replaced with the refresh token, and every hour Twitch is asked whether it still works, as Twitch asks of apps.
 */
final class BotToken
{
    private const PLATFORM = 'twitch';
    // a token with less time left than this is replaced before it is used, so it never dies on the way
    private const REFRESH_MARGIN_SECONDS = 300;
    private const VALIDATE_SECONDS = 3600;

    private ?PDO $pdo = null;
    private int $validatedAt = 0;

    public function __construct(private Database $database, private TwitchClient $twitch, private Secrets $secrets)
    {
    }

    /** Whether someone has authorized the bot's account yet, at /auth/twitch/bot. */
    public function isAuthorized(): bool
    {
        return $this->read() !== null;
    }

    /** @throws RuntimeException when the account is not authorized yet */
    public function userId(): string
    {
        return $this->account()['userId'];
    }

    /**
     * A token that works now.
     *
     * @throws RuntimeException when the account is not authorized, or Twitch no longer takes its tokens,
     *     such as after the account took the access back
     */
    public function get(): string
    {
        $account = $this->account();
        $token = $this->secrets->open($account['accessToken']);

        if ($account['expiresAt'] - time() <= self::REFRESH_MARGIN_SECONDS) {
            return $this->replace($account);
        }

        if (time() - $this->validatedAt < self::VALIDATE_SECONDS) {
            return $token;
        }

        try {
            $this->twitch->validate($token);
            $this->validatedAt = time();

            return $token;
        } catch (RuntimeException $error) {
            // anything but a refusal is not the token's fault, and a new token would not help
            if (!TwitchClient::isUnauthorized($error)) {
                throw $error;
            }
        }

        return $this->replace($account);
    }

    /**
     * @param array{refreshToken: string} $account
     */
    private function replace(array $account): string
    {
        $tokens = $this->twitch->refresh($this->secrets->open($account['refreshToken']));

        try {
            (new BotAccountRepository($this->connection()))->saveTokens(
                self::PLATFORM,
                $this->secrets->seal($tokens['refreshToken']),
                $this->secrets->seal($tokens['accessToken']),
                time() + $tokens['expiresIn'],
            );
        } catch (PDOException $error) {
            $this->pdo = null;

            throw $error;
        }

        // the token has just come from Twitch, so it needs no check for an hour
        $this->validatedAt = time();

        return $tokens['accessToken'];
    }

    /** @return array{userId: string, name: string, refreshToken: string, accessToken: string, expiresAt: int} */
    private function account(): array
    {
        return $this->read() ?? throw new RuntimeException('The bot account is not authorized yet: open /auth/twitch/bot as the bot account');
    }

    private function read(): ?array
    {
        try {
            return (new BotAccountRepository($this->connection()))->find(self::PLATFORM);
        } catch (PDOException $error) {
            // a new connection at the next use, so a broken one is not kept
            $this->pdo = null;

            throw $error;
        }
    }

    private function connection(): PDO
    {
        return $this->pdo ??= $this->database->connect();
    }
}
