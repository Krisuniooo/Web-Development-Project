<?php

declare(strict_types=1);

namespace App\Controller;

use App\Discord\DiscordClient;
use App\Twitch\TwitchClient;
use RuntimeException;

/**
 * Logging in through a platform and logging out. Each platform has a route that sends the browser to it
 * and a route it sends the browser back to; what they share is below them.
 */
final class AuthController extends AppController
{
    // random bytes in the state of a login: enough that nobody guesses it
    private const STATE_BYTES = 16;

    /** Sends the browser to Discord, which asks the user for consent. */
    public function discord(): void
    {
        if (!getenv('DISCORD_CLIENT_ID') || !getenv('DISCORD_CLIENT_SECRET') || !getenv('APP_URL')) {
            error_log('Discord login needs DISCORD_CLIENT_ID, DISCORD_CLIENT_SECRET and APP_URL in .env');
            $this->redirect('/login?error=not_configured');
        }

        $this->redirect(DiscordClient::AUTHORIZE_URL . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => getenv('DISCORD_CLIENT_ID'),
            // guilds: the servers the user is on, so the panel can offer to add the bot to them
            'scope' => 'identify guilds',
            // someone who already agreed goes straight back, without the consent screen
            'prompt' => 'none',
            'redirect_uri' => $this->discordRedirectUri(),
            'state' => $this->newState(),
        ]));
    }

    /** Discord sends the browser back here, with a code or with the reason there is none. */
    public function discordCallback(): void
    {
        $code = $this->returnedCode();

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

        $this->logIn(['platform' => 'discord'] + $user, $servers);
    }

    /** Sends the browser to Twitch, which asks the user for consent the first time. */
    public function twitch(): void
    {
        if (!TwitchClient::isConfigured()) {
            error_log('Twitch login needs TWITCH_CLIENT_ID, TWITCH_CLIENT_SECRET and APP_URL in .env');
            $this->redirect('/login?error=not_configured');
        }

        $this->redirect(TwitchClient::fromEnvironment()->authorizeUrl($this->newState()));
    }

    /** Twitch sends the browser back here, with a code or with the reason there is none. */
    public function twitchCallback(): void
    {
        $code = $this->returnedCode();

        try {
            $twitch = TwitchClient::fromEnvironment();
            $token = $twitch->exchangeCode($code);
            $user = $twitch->fetchUser($token['accessToken']);
            $channels = $twitch->fetchManagedChannels($token['accessToken'], $user);
        } catch (RuntimeException $exception) {
            error_log('Twitch login failed: ' . $exception->getMessage());
            $this->redirect('/login?error=failed');
        }

        // unlike Discord's, the token stays, in the session only: on Twitch nothing else can say whether the user
        // still moderates a channel. The login ends when the token does, so the panel never asks with a dead one.
        $this->logIn(['platform' => 'twitch'] + $user, $channels, [
            'platformToken' => $token['accessToken'],
            'expiresAt' => time() + $token['expiresIn'],
        ]);
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

    /** The state of a new login, remembered in the session: the platform sends it back with the user. */
    private function newState(): string
    {
        $this->startSession();

        return $_SESSION['oauth_state'] = bin2hex(random_bytes(self::STATE_BYTES));
    }

    /**
     * The one-time code a platform sent back with the user. A return that does not belong to a login
     * started in this browser, a refusal and a missing code all end on the login page.
     */
    private function returnedCode(): string
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

        return $code;
    }

    /**
     * Starts the logged-in session and opens the panel.
     *
     * @param array{platform: string, id: string, name: string, avatarUrl: ?string} $user
     * @param list<array{id: string, name: string, iconUrl: ?string}> $servers what the user could manage at this login
     * @param array<string, mixed> $extra what only this platform's session holds
     */
    private function logIn(array $user, array $servers, array $extra = []): never
    {
        // a new session id, so a session prepared by someone else cannot become a logged-in one
        session_regenerate_id(true);
        // only this login's data, so nothing of an account logged in before stays
        $_SESSION = $extra + [
            // the account is kept only here, not in the database
            'user' => $user,
            // a snapshot from this login, only for showing the list: access is checked again before any change
            'servers' => $servers,
            'lastActivity' => time(),
        ];

        $this->redirect('/app');
    }

    private function discordClient(): DiscordClient
    {
        return new DiscordClient(
            (string) getenv('DISCORD_CLIENT_ID'),
            (string) getenv('DISCORD_CLIENT_SECRET'),
            $this->discordRedirectUri(),
            DiscordClient::apiUrl(),
        );
    }

    private function discordRedirectUri(): string
    {
        return rtrim((string) getenv('APP_URL'), '/') . '/auth/discord/callback';
    }
}
