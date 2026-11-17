<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database;
use App\Discord\DiscordClient;
use App\Repository\BotAccountRepository;
use App\Secrets;
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
    // what a state was made for: someone logging in, or the bot's account agreeing to act as the bot
    private const PURPOSE_LOGIN = 'login';
    private const PURPOSE_BOT = 'bot';
    // the owner of a channel agreeing that the bot is made a moderator of it, when connecting the channel
    private const PURPOSE_CONNECT = 'connect';
    // how the page after the bot's account agreed can end
    private const BOT_RESULTS = ['ok', 'wrong_account', 'failed', 'not_configured'];
    private const BOT_RESULT_URL = '/auth/twitch/bot/result?status=';

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

        $this->redirect(TwitchClient::fromEnvironment()->authorizeUrl($this->newState(self::PURPOSE_LOGIN)));
    }

    /**
     * Sends the browser to Twitch for the bot's own account to agree to act as the bot. Anyone can start it, but it ends
     * only when the account named in TWITCH_BOT_LOGIN comes back, so nobody else can become the bot.
     */
    public function twitchBot(): void
    {
        if (!TwitchClient::isConfigured() || $this->botLogin() === '') {
            error_log('The Twitch bot account needs TWITCH_CLIENT_ID, TWITCH_CLIENT_SECRET, TWITCH_BOT_LOGIN and APP_URL in .env');
            $this->redirect(self::BOT_RESULT_URL . 'not_configured');
        }

        $this->redirect(TwitchClient::fromEnvironment()->botAuthorizeUrl($this->newState(self::PURPOSE_BOT)));
    }

    /**
     * Sends the owner of a channel to Twitch to agree that the bot is made a moderator of it: the step after "Connect"
     * in the panel, which has put the channel in the session. For anyone who did not just ask to connect their own
     * channel there, this address does nothing.
     */
    public function twitchConnect(): void
    {
        $user = $this->requireLogin();

        if ($user['platform'] !== 'twitch' || ($_SESSION['pendingConnect'] ?? null) !== $user['id'] || !TwitchClient::isConfigured()) {
            $this->redirect('/app/servers');
        }

        $this->redirect(TwitchClient::fromEnvironment()->connectAuthorizeUrl($this->newState(self::PURPOSE_CONNECT)));
    }

    /** What happened to the bot's account, said in words; the page can be opened again without doing anything twice. */
    public function twitchBotResult(): void
    {
        $status = $_GET['status'] ?? null;

        if (!in_array($status, self::BOT_RESULTS, true)) {
            $this->notFound();
            return;
        }

        $botName = null;

        if ($status === 'ok') {
            try {
                $botName = (new BotAccountRepository((new Database())->connect()))->findName('twitch');
            } catch (RuntimeException $exception) {
                error_log('Could not read the name of the bot account: ' . $exception->getMessage());
            }

            // an address that says the account is ready, while none is saved, must not say so
            $status = $botName === null ? 'failed' : 'ok';
        }

        $this->render('twitch-bot', [
            'pageTitle' => $this->lang()->text('twitchbot.title'),
            'status' => $status,
            'botName' => $botName,
        ]);
    }

    /** Twitch sends the browser back here, with a code or with the reason there is none. */
    public function twitchCallback(): void
    {
        $code = $this->returnedCode();
        $purpose = $_SESSION['oauth_purpose'] ?? null;
        unset($_SESSION['oauth_purpose']);

        if ($purpose === self::PURPOSE_BOT) {
            $this->authorizeBot($code);
        }

        if ($purpose === self::PURPOSE_CONNECT) {
            $this->finishConnect($code);
        }

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

    /** The state of a new login, remembered in the session with what it is for: the platform sends it back with the user. */
    private function newState(string $purpose = self::PURPOSE_LOGIN): string
    {
        $this->startSession();
        $_SESSION['oauth_purpose'] = $purpose;

        return $_SESSION['oauth_state'] = bin2hex(random_bytes(self::STATE_BYTES));
    }

    /**
     * Saves the account that agreed as the one the bot acts as, with its tokens sealed. Only the account named in
     * TWITCH_BOT_LOGIN is taken: whoever else got as far as this, the bot would act as them.
     */
    private function authorizeBot(string $code): never
    {
        try {
            $twitch = TwitchClient::fromEnvironment();
            $tokens = $twitch->exchangeCode($code);
            $account = $twitch->validate($tokens['accessToken']);
            $user = $twitch->fetchUser($tokens['accessToken']);
        } catch (RuntimeException $exception) {
            error_log('Twitch bot authorization failed: ' . $exception->getMessage());
            $this->redirect(self::BOT_RESULT_URL . 'failed');
        }

        if ($this->botLogin() === '' || strcasecmp($account['login'], $this->botLogin()) !== 0) {
            error_log('Twitch bot authorization refused: the account that agreed is not the one named in TWITCH_BOT_LOGIN');
            $this->redirect(self::BOT_RESULT_URL . 'wrong_account');
        }

        // Twitch gives a refresh token to apps with a secret; without one the bot would stop after a few hours
        if ($tokens['refreshToken'] === null) {
            error_log('Twitch bot authorization failed: Twitch gave no refresh token');
            $this->redirect(self::BOT_RESULT_URL . 'failed');
        }

        try {
            $secrets = Secrets::fromEnvironment();
            (new BotAccountRepository((new Database())->connect()))->save(
                'twitch',
                $account['userId'],
                $user['name'],
                $secrets->seal($tokens['refreshToken']),
                $secrets->seal($tokens['accessToken']),
                time() + $tokens['expiresIn'],
            );
        } catch (RuntimeException $exception) {
            error_log('Twitch bot authorization failed: ' . strtok($exception->getMessage(), "\n"));
            $this->redirect(self::BOT_RESULT_URL . 'failed');
        }

        $this->redirect(self::BOT_RESULT_URL . 'ok');
    }

    /**
     * The owner agreed that the bot is made a moderator: it is done with the owner's token, which is then forgotten,
     * and the result goes to the panel, which connects the channel. Only the owner's own account counts, so someone
     * else logged in to Twitch in the same browser cannot make the bot a moderator of a channel that is not theirs.
     */
    private function finishConnect(string $code): never
    {
        $user = $this->loggedInUser();
        $pending = $_SESSION['pendingConnect'] ?? null;
        unset($_SESSION['pendingConnect']);

        // the login ended meanwhile, or this is not the connection the panel asked for
        if ($user === null || $user['platform'] !== 'twitch' || $pending !== $user['id']) {
            $this->redirect('/login');
        }

        try {
            $twitch = TwitchClient::fromEnvironment();
            $token = $twitch->exchangeCode($code)['accessToken'];
            $account = $twitch->validate($token);
        } catch (RuntimeException $exception) {
            error_log('Twitch connecting a channel failed: ' . $exception->getMessage());
            $this->refuseConnecting('servers.connect_error');
        }

        // another account than the one in the panel agreed, since the browser is logged in to Twitch as someone else
        if ($account['userId'] !== $user['id']) {
            $this->refuseConnecting('connect.wrong_account');
        }

        try {
            $bot = (new BotAccountRepository((new Database())->connect()))->findIdentity('twitch');

            if ($bot === null) {
                $this->refuseConnecting('connect.no_bot');
            }

            $moderator = $twitch->addModerator($token, $user['id'], $bot['userId']);
        } catch (RuntimeException $exception) {
            error_log('Twitch could not make the bot a moderator: ' . strtok($exception->getMessage(), "\n"));
            // the channel is connected all the same, and its owner is told to add the bot by hand
            $moderator = 'failed';
        }

        $_SESSION['connectResult'] = ['serverId' => $user['id'], 'moderator' => $moderator];

        $this->redirect('/app/servers/connected');
    }

    /** Back to the list of channels with the reason nothing was connected. */
    private function refuseConnecting(string $notice): never
    {
        $_SESSION['notice'] = ['text' => $notice, 'isError' => true];

        $this->redirect('/app/servers');
    }

    /** The login of the account that may become the bot, from .env. */
    private function botLogin(): string
    {
        return trim((string) getenv('TWITCH_BOT_LOGIN'));
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
