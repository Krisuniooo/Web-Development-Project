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
    private const BOT_RESULTS = ['ok', 'wrong_account', 'other_account', 'cancelled', 'failed', 'not_configured'];
    private const BOT_RESULT_URL = '/auth/twitch/bot/result?status=';
    // why a channel is not connected after its owner came back from Twitch, and what they are told
    private const CONNECT_REFUSALS = [
        'error' => 'servers.connect_error',
        'wrong_account' => 'connect.wrong_account',
        'no_bot' => 'connect.no_bot',
    ];

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
        [$code, $error] = $this->returnedCode();

        if ($code === null) {
            $this->redirect('/login?error=' . $error);
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
     * only when the account named in TWITCH_BOT_LOGIN comes back, and once an account is saved, only when that same
     * account does, so nobody else can become the bot.
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
     * in the panel, which has put the channel in the session. It works once, right after that form, so for anyone
     * else, and for a link from another site later, this address does nothing.
     */
    public function twitchConnect(): void
    {
        $user = $this->requireLogin();
        $isRequested = ($_SESSION['pendingConnect'] ?? null) === $user['id'];
        unset($_SESSION['pendingConnect']);

        if ($user['platform'] !== 'twitch' || !$isRequested || !TwitchClient::isConfigured()) {
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
        $this->startSession();
        // what the browser went to Twitch for, which also says where it goes back to when Twitch sent no code
        $purpose = $_SESSION['oauth_purpose'] ?? self::PURPOSE_LOGIN;
        unset($_SESSION['oauth_purpose']);
        [$code, $error] = $this->returnedCode();

        if ($code === null) {
            $this->twitchFailed($purpose, $error);
        }

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

        // the token of a login before this one in the browser, such as before reading the list of channels anew
        $this->revokeLoginToken();

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
            $this->revokeLoginToken();
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

    /** Back to where the step Twitch did not finish began, with why: "cancelled" when the person said no, or "failed". */
    private function twitchFailed(string $purpose, string $error): never
    {
        if ($purpose === self::PURPOSE_BOT) {
            $this->redirect(self::BOT_RESULT_URL . $error);
        }

        if ($purpose === self::PURPOSE_CONNECT) {
            $this->refuseConnecting($error === 'cancelled' ? 'connect.cancelled' : 'servers.connect_error');
        }

        $this->redirect('/login?error=' . $error);
    }

    /** Saves the account that agreed as the one the bot acts as; the tokens of an account that is not saved are revoked. */
    private function authorizeBot(string $code): never
    {
        $twitch = TwitchClient::fromEnvironment();

        try {
            $tokens = $twitch->exchangeCode($code);
        } catch (RuntimeException $exception) {
            error_log('Twitch bot authorization failed: ' . $exception->getMessage());
            $this->redirect(self::BOT_RESULT_URL . 'failed');
        }

        $result = $this->saveBotAccount($twitch, $tokens);

        if ($result !== 'ok') {
            $twitch->revoke($tokens['accessToken']);
        }

        $this->redirect(self::BOT_RESULT_URL . $result);
    }

    /**
     * Saves the account that agreed as the one the bot acts as, with its tokens sealed. Only the account named in
     * TWITCH_BOT_LOGIN is taken: whoever else got as far as this, the bot would act as them. Once an account is saved,
     * only the same one is taken again, by its id, since a login can change hands after the bot's account renames.
     *
     * @param array{accessToken: string, refreshToken: ?string, expiresIn: int} $tokens
     * @return 'ok'|'wrong_account'|'other_account'|'failed'
     */
    private function saveBotAccount(TwitchClient $twitch, array $tokens): string
    {
        try {
            $account = $twitch->validate($tokens['accessToken']);
            $user = $twitch->fetchUser($tokens['accessToken']);
        } catch (RuntimeException $exception) {
            error_log('Twitch bot authorization failed: ' . $exception->getMessage());

            return 'failed';
        }

        if ($this->botLogin() === '' || strcasecmp($account['login'], $this->botLogin()) !== 0) {
            error_log('Twitch bot authorization refused: the account that agreed is not the one named in TWITCH_BOT_LOGIN');

            return 'wrong_account';
        }

        // Twitch gives a refresh token to apps with a secret; without one the bot would stop after a few hours
        if ($tokens['refreshToken'] === null) {
            error_log('Twitch bot authorization failed: Twitch gave no refresh token');

            return 'failed';
        }

        try {
            $secrets = Secrets::fromEnvironment();
            $isSaved = (new BotAccountRepository((new Database())->connect()))->save(
                'twitch',
                $account['userId'],
                $user['name'],
                $secrets->seal($tokens['refreshToken']),
                $secrets->seal($tokens['accessToken']),
                time() + $tokens['expiresIn'],
            );
        } catch (RuntimeException $exception) {
            error_log('Twitch bot authorization failed: ' . strtok($exception->getMessage(), "\n"));

            return 'failed';
        }

        if (!$isSaved) {
            error_log('Twitch bot authorization refused: the bot already acts as another account; remove it from bot_accounts first to change it');

            return 'other_account';
        }

        return 'ok';
    }

    /**
     * The owner agreed that the bot is made a moderator: it is done with the owner's token, which is revoked right after,
     * whatever happened, and the result goes to the panel, which connects the channel. The state of this step was made
     * only by twitchConnect(), for the login of now, since a new login replaces the whole session.
     */
    private function finishConnect(string $code): never
    {
        $user = $this->loggedInUser();

        // the login ended meanwhile
        if ($user === null || $user['platform'] !== 'twitch') {
            $this->redirect('/login');
        }

        $twitch = TwitchClient::fromEnvironment();

        try {
            $token = $twitch->exchangeCode($code)['accessToken'];
        } catch (RuntimeException $exception) {
            error_log('Twitch connecting a channel failed: ' . $exception->getMessage());
            $this->refuseConnecting('servers.connect_error');
        }

        // the token may change who moderates the channel, so it serves this one step and no longer
        $outcome = $this->makeBotModerator($twitch, $token, $user['id']);
        $twitch->revoke($token);

        if (isset(self::CONNECT_REFUSALS[$outcome])) {
            $this->refuseConnecting(self::CONNECT_REFUSALS[$outcome]);
        }

        $_SESSION['connectResult'] = ['serverId' => $user['id'], 'moderator' => $outcome];

        $this->redirect('/app/servers/connected');
    }

    /**
     * Makes the bot a moderator of the owner's channel with the owner's token. Only the owner's own account counts,
     * so someone else logged in to Twitch in the same browser cannot make the bot a moderator of a channel that is not theirs.
     *
     * @return 'added'|'already'|'vip'|'failed'|'error'|'wrong_account'|'no_bot' what Twitch said about the moderator,
     *     after which the channel is connected, or a key of CONNECT_REFUSALS, after which it is not
     */
    private function makeBotModerator(TwitchClient $twitch, string $ownerToken, string $ownerId): string
    {
        try {
            if ($twitch->validate($ownerToken)['userId'] !== $ownerId) {
                return 'wrong_account';
            }
        } catch (RuntimeException $exception) {
            error_log('Twitch connecting a channel failed: ' . $exception->getMessage());

            return 'error';
        }

        try {
            $bot = (new BotAccountRepository((new Database())->connect()))->findIdentity('twitch');

            if ($bot === null) {
                return 'no_bot';
            }

            return $twitch->addModerator($ownerToken, $ownerId, $bot['userId']);
        } catch (RuntimeException $exception) {
            error_log('Twitch could not make the bot a moderator: ' . strtok($exception->getMessage(), "\n"));

            // the channel is connected all the same, and its owner is told to add the bot by hand
            return 'failed';
        }
    }

    /** Back to the list of channels with the reason nothing was connected. */
    private function refuseConnecting(string $notice): never
    {
        $_SESSION['notice'] = ['text' => $notice, 'isError' => true];

        $this->redirect('/app/servers');
    }

    /** Makes the token of the Twitch login in this session stop working, when the session no longer needs it. */
    private function revokeLoginToken(): void
    {
        $this->startSession();
        $token = $_SESSION['platformToken'] ?? null;

        if (($_SESSION['user']['platform'] ?? null) === 'twitch' && is_string($token) && TwitchClient::isConfigured()) {
            TwitchClient::fromEnvironment()->revoke($token);
        }
    }

    /** The login of the account that may become the bot, from .env. */
    private function botLogin(): string
    {
        return trim((string) getenv('TWITCH_BOT_LOGIN'));
    }

    /**
     * The one-time code a platform sent back with the user, or why there is none: "cancelled" when they said no,
     * and "failed" for a return that does not belong to a login started in this browser, and for one without a code.
     *
     * @return array{string, null}|array{null, 'cancelled'|'failed'}
     */
    private function returnedCode(): array
    {
        $this->startSession();

        $expected = $_SESSION['oauth_state'] ?? null;
        $state = $_GET['state'] ?? null;
        // a state works once, so a copied link cannot be replayed
        unset($_SESSION['oauth_state']);

        // the state proves this return belongs to a login started in this browser
        if (!is_string($expected) || !is_string($state) || !hash_equals($expected, $state)) {
            return [null, 'failed'];
        }

        if (isset($_GET['error'])) {
            return [null, $_GET['error'] === 'access_denied' ? 'cancelled' : 'failed'];
        }

        $code = $_GET['code'] ?? null;

        return is_string($code) && $code !== '' ? [$code, null] : [null, 'failed'];
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
