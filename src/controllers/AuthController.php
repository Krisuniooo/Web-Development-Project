<?php

declare(strict_types=1);

namespace App\Controller;

final class AuthController extends AppController
{
    private const DISCORD_AUTHORIZE_URL = 'https://discord.com/oauth2/authorize';

    /** Sends the browser to Discord, which asks the user for consent. */
    public function discord(): void
    {
        $clientId = getenv('DISCORD_CLIENT_ID');
        $appUrl = getenv('APP_URL');

        if (!$clientId || !$appUrl) {
            error_log('Discord login needs DISCORD_CLIENT_ID and APP_URL in .env');
            $this->redirect('/login?error=not_configured');
        }

        $this->startSession();
        $_SESSION['oauth_state'] = bin2hex(random_bytes(16));

        $this->redirect(self::DISCORD_AUTHORIZE_URL . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'scope' => 'identify',
            'redirect_uri' => rtrim($appUrl, '/') . '/auth/discord/callback',
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

        if (!is_string($_GET['code'] ?? null)) {
            $this->redirect('/login?error=failed');
        }

        $this->redirect('/app');
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
