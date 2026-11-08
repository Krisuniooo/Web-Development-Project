<?php

declare(strict_types=1);

namespace App\Controller;

use App\DiscordClient;
use RuntimeException;

final class AuthController extends AppController
{
    private const DISCORD_AUTHORIZE_URL = 'https://discord.com/oauth2/authorize';

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
            // guilds: the servers the user is on, so the panel can offer to add the bot to them
            'scope' => 'identify guilds',
            // someone who already agreed goes straight back, without the consent screen
            'prompt' => 'none',
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
            $discord = $this->discordClient();
            // the token is used for these two questions and then forgotten
            $accessToken = $discord->exchangeCode($code);
            $user = $discord->fetchUser($accessToken);
            $servers = $discord->fetchManagedServers($accessToken);
        } catch (RuntimeException $exception) {
            error_log('Discord login failed: ' . $exception->getMessage());
            $this->redirect('/login?error=failed');
        }

        // a new session id, so a session prepared by someone else cannot become a logged-in one
        session_regenerate_id(true);
        // the account is kept only here, not in the database
        $_SESSION['user'] = ['platform' => 'discord', 'id' => $user['id'], 'name' => $user['name'], 'avatarUrl' => $user['avatarUrl']];
        // a snapshot from this login, only for showing the list: access is checked again before any change
        $_SESSION['servers'] = $servers;

        $this->redirect('/app');
    }

    public function logout(): void
    {
        // checked before the session starts: a cross-site POST arrives without the Lax cookie,
        // so PHP would create a new session and overwrite the visitor's real one
        if (!$this->isSameOriginRequest()) {
            http_response_code(403);
            return;
        }

        if (isset($_COOKIE[session_name()])) {
            $this->endSession();
        }

        $this->redirect('/');
    }

    private function discordClient(): DiscordClient
    {
        return new DiscordClient(
            (string) getenv('DISCORD_CLIENT_ID'),
            (string) getenv('DISCORD_CLIENT_SECRET'),
            $this->discordRedirectUri(),
            getenv('DISCORD_API_URL') ?: DiscordClient::API_URL,
        );
    }

    private function discordRedirectUri(): string
    {
        return rtrim((string) getenv('APP_URL'), '/') . '/auth/discord/callback';
    }
}
