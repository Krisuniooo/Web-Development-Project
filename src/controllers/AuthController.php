<?php

declare(strict_types=1);

namespace App\Controller;

use App\AccountRepository;
use App\Database;
use App\DiscordClient;
use RuntimeException;

final class AuthController extends AppController
{
    private const DISCORD_AUTHORIZE_URL = 'https://discord.com/oauth2/authorize';
    private const DISCORD_API_URL = 'https://discord.com/api/v10';

    /** Sends the browser to Discord, which asks the user for consent. */
    public function discord(): void
    {
        if (!getenv('DISCORD_CLIENT_ID') || !getenv('DISCORD_CLIENT_SECRET') || !getenv('APP_URL')) {
            error_log('Discord login needs DISCORD_CLIENT_ID, DISCORD_CLIENT_SECRET and APP_URL in .env');
            $this->redirect('/login?error=not_configured');
        }

        $this->startSession();
        $_SESSION['oauth_state'] = bin2hex(random_bytes(16));

        $this->redirect(self::DISCORD_AUTHORIZE_URL . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => getenv('DISCORD_CLIENT_ID'),
            'scope' => 'identify',
            'redirect_uri' => $this->discordRedirectUri(),
            'state' => $_SESSION['oauth_state'],
        ]));
    }

    /** Discord sends the browser back here, with a code or with the reason there is none. */
    public function discordCallback(): void
    {
        $this->startSession();

        $expected = $_SESSION['oauth_state'] ?? null;
        $state = $_GET['state'] ?? null;
        // a state works once, so a copied link cannot be replayed
        unset($_SESSION['oauth_state']);

        // the state proves this return belongs to a login started in this browser
        if (!is_string($expected) || !is_string($state) || !hash_equals($expected, $state)) {
            $this->redirect('/login?error=failed');
        }

        if (isset($_GET['error'])) {
            $this->redirect('/login?error=' . ($_GET['error'] === 'access_denied' ? 'cancelled' : 'failed'));
        }

        $code = $_GET['code'] ?? null;

        if (!is_string($code) || $code === '') {
            $this->redirect('/login?error=failed');
        }

        try {
            $accountId = $this->saveDiscordAccount($code);
        } catch (RuntimeException $exception) {
            error_log('Discord login failed: ' . $exception->getMessage());
            $this->redirect('/login?error=failed');
        }

        // a new session id, so a session prepared by someone else cannot become a logged-in one
        session_regenerate_id(true);
        $_SESSION['account_id'] = $accountId;

        $this->redirect('/app');
    }

    private function saveDiscordAccount(string $code): int
    {
        $discord = new DiscordClient(
            (string) getenv('DISCORD_CLIENT_ID'),
            (string) getenv('DISCORD_CLIENT_SECRET'),
            $this->discordRedirectUri(),
            getenv('DISCORD_API_URL') ?: self::DISCORD_API_URL,
        );

        // the token is used for this one question and then forgotten
        $user = $discord->fetchUser($discord->exchangeCode($code));

        return (new AccountRepository((new Database())->connect()))
            ->saveFromLogin('discord', $user['id'], $user['name']);
    }

    private function discordRedirectUri(): string
    {
        return rtrim((string) getenv('APP_URL'), '/') . '/auth/discord/callback';
    }

    private function startSession(): void
    {
        session_start([
            'use_strict_mode' => true,
            'cookie_httponly' => true,
            // Strict would drop the cookie when Discord sends the browser back
            'cookie_samesite' => 'Lax',
            'cookie_secure' => !empty($_SERVER['HTTPS']),
        ]);
    }
}
