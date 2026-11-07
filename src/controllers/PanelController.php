<?php

declare(strict_types=1);

namespace App\Controller;

use App\ChannelRepository;
use App\Database;
use App\MessageClassifier;
use App\ServerRepository;
use RuntimeException;

final class PanelController extends AppController
{
    private const DISCORD_INVITE_URL = 'https://discord.com/oauth2/authorize';
    // asked for all at once, so turning on timeouts or bans later needs no new invite
    private const DISCORD_BOT_PERMISSIONS = (1 << 10) // VIEW_CHANNEL: see the moderated channels
        | (1 << 13) // MANAGE_MESSAGES: delete messages
        | (1 << 40) // MODERATE_MEMBERS: time out authors
        | (1 << 2); // BAN_MEMBERS: ban authors
    // the model of the test box, until a channel can choose its own
    private const TEST_MODEL = 'basal-mini';
    // the longest message Discord allows without Nitro
    private const TEST_MAX_LENGTH = 2000;
    // for one login, because the bot uses the same model
    private const TEST_CHECKS_PER_MINUTE = 10;

    public function overview(): void
    {
        $this->renderSection('overview');
    }

    public function channels(): void
    {
        $this->renderSection('channels');
    }

    public function models(): void
    {
        $this->renderSection('models');
    }

    public function limits(): void
    {
        $this->renderSection('limits');
    }

    public function logs(): void
    {
        $this->renderSection('logs');
    }

    public function settings(): void
    {
        $this->renderSection('settings');
    }

    /** Chooses the server the other sections are about. */
    public function manage(): void
    {
        if (!$this->isSameOriginRequest()) {
            http_response_code(403);
            return;
        }

        $user = $this->requireLogin();
        $serverId = $_POST['server'] ?? null;
        $isOwnServer = is_string($serverId) && in_array($serverId, array_column($this->sessionServers(), 'id'), true);

        // only a server the user manages, and only with the bot on it, since the settings are for the bot
        if ($isOwnServer && $this->serverIdsWithBot($user['platform'], [$serverId]) === [$serverId]) {
            $_SESSION['managedServerId'] = $serverId;
        }

        $this->redirect('/app');
    }

    /** For the test box: whether the model can check a message now. */
    public function modelStatus(): void
    {
        if ($this->loggedInUser() === null) {
            $this->json(['error' => $this->lang()->text('check.error.login')], 401);
            return;
        }

        // the session is only read here, so the user's other tabs need not wait for the model
        session_write_close();

        if (!(new MessageClassifier(self::TEST_MODEL))->isReady()) {
            $this->json(['ready' => false, 'error' => $this->lang()->text('check.error.model')], 503);
            return;
        }

        $this->json(['ready' => true]);
    }

    /** For the test box: what the model says about a message, and what each level would decide. */
    public function checkMessage(): void
    {
        $lang = $this->lang();

        if (!$this->isSameOriginRequest()) {
            $this->json(['error' => $lang->text('check.error.forbidden')], 403);
            return;
        }

        $user = $this->loggedInUser();

        if ($user === null) {
            $this->json(['error' => $lang->text('check.error.login')], 401);
            return;
        }

        $servers = $this->sessionServers();

        // the same rule as the sections: a chosen server that still has the bot
        if ($this->managedServer($servers, $this->serverIdsWithBot($user['platform'], array_column($servers, 'id'))) === null) {
            $this->json(['error' => $lang->text('check.error.server')], 403);
            return;
        }

        $classifier = new MessageClassifier(self::TEST_MODEL);
        $request = json_decode((string) file_get_contents('php://input'), true);
        $text = is_array($request) ? ($request['text'] ?? null) : null;
        $language = is_array($request) ? ($request['language'] ?? null) : null;

        if (!is_string($text) || trim($text) === '' || mb_strlen($text) > self::TEST_MAX_LENGTH) {
            $this->json(['error' => sprintf($lang->text('check.error.text'), self::TEST_MAX_LENGTH)], 400);
            return;
        }

        if (!in_array($language, $classifier->languages(), true)) {
            $this->json(['error' => $lang->text('check.error.language')], 400);
            return;
        }

        // the checks of the last minute; a request turned down above does not count
        $now = time();
        $times = is_array($_SESSION['messageChecks'] ?? null) ? $_SESSION['messageChecks'] : [];
        $recent = array_values(array_filter($times, fn(mixed $time): bool => is_int($time) && $time > $now - 60));

        if (count($recent) >= self::TEST_CHECKS_PER_MINUTE) {
            // seconds until the oldest of them is a minute old
            header('Retry-After: ' . ($recent[0] + 60 - $now));
            $this->json(['error' => $lang->text('check.error.limit')], 429);
            return;
        }

        $_SESSION['messageChecks'] = [...$recent, $now];
        // the model takes seconds, and an open session would make the user's other tabs wait
        session_write_close();

        try {
            $scores = $classifier->scores($text, $language);
        } catch (RuntimeException $exception) {
            error_log('Panel could not check a message: ' . $exception->getMessage());
            $this->json(['error' => $lang->text('check.error.model')], 503);
            return;
        }

        $brokenByLevel = [];

        foreach (MessageClassifier::LEVELS as $level) {
            // every rule at this level, as if the channel had them all set the same
            $brokenByLevel[$level] = $classifier->brokenRules($scores, $language, array_fill_keys(array_keys($scores), $level));
        }

        $categories = [];

        foreach ($scores as $category => $score) {
            $categories[] = [
                'key' => $category,
                'name' => $lang->text('category.' . $category),
                'score' => round($score, 4),
                // by level: whether the message breaks this rule there
                'breaks' => array_map(fn(array $broken): bool => in_array($category, $broken, true), $brokenByLevel),
            ];
        }

        $this->json(['categories' => $categories]);
    }

    /** @return array{platform: string, id: string, name: string, avatarUrl: ?string} */
    private function requireLogin(): array
    {
        $user = $this->loggedInUser();

        if ($user === null) {
            $this->redirect('/login');
        }

        return $user;
    }

    /** @return list<array{id: string, name: string, icon: ?string}> the servers saved at login */
    private function sessionServers(): array
    {
        $servers = $_SESSION['servers'] ?? [];

        return is_array($servers) ? $servers : [];
    }

    /**
     * The chosen server, checked again on every page: it must still be on the user's list and still have the bot.
     *
     * @param list<string> $idsWithBot
     * @return array{id: string, name: string}|null
     */
    private function managedServer(array $servers, array $idsWithBot): ?array
    {
        $id = $_SESSION['managedServerId'] ?? null;

        foreach ($servers as $server) {
            if ($server['id'] === $id && in_array($id, $idsWithBot, true)) {
                return ['id' => $server['id'], 'name' => $server['name']];
            }
        }

        return null;
    }

    /**
     * Each server with a link that invites the bot, whether the bot is already there and whether it is the chosen one.
     *
     * @param list<string> $idsWithBot
     * @return list<array{id: string, name: string, initials: string, iconUrl: ?string, inviteUrl: string, hasBot: bool, isManaged: bool}>
     */
    private function serverCards(array $servers, array $idsWithBot, ?array $managedServer): array
    {
        $list = [];

        foreach ($servers as $server) {
            // shown instead of a missing icon: the first letters of the first two words, as on Discord
            $words = array_slice(preg_split('/\s+/u', trim($server['name']), -1, PREG_SPLIT_NO_EMPTY), 0, 2);

            $list[] = [
                'id' => $server['id'],
                'name' => $server['name'],
                'initials' => mb_strtoupper(implode('', array_map(fn(string $word): string => mb_substr($word, 0, 1), $words))),
                'iconUrl' => $server['icon'] === null
                    ? null
                    : "https://cdn.discordapp.com/icons/{$server['id']}/{$server['icon']}.png?size=64",
                'inviteUrl' => self::DISCORD_INVITE_URL . '?' . http_build_query([
                    'client_id' => getenv('DISCORD_CLIENT_ID'),
                    'scope' => 'bot',
                    'permissions' => self::DISCORD_BOT_PERMISSIONS,
                    // the server is already chosen on Discord's page
                    'guild_id' => $server['id'],
                    'disable_guild_select' => 'true',
                ]),
                'hasBot' => in_array($server['id'], $idsWithBot, true),
                'isManaged' => $server['id'] === ($managedServer['id'] ?? null),
            ];
        }

        return $list;
    }

    /**
     * Asks the database which of these servers have the bot. The bot keeps that list up to date.
     *
     * @param list<string> $serverIds
     * @return list<string>
     */
    private function serverIdsWithBot(string $platform, array $serverIds): array
    {
        if ($serverIds === []) {
            return [];
        }

        try {
            return (new ServerRepository((new Database())->connect()))->findActiveIds($platform, $serverIds);
        } catch (RuntimeException $exception) {
            // the servers come from the session, so the list still shows, only without the bot status
            error_log('Panel could not check which servers have the bot: ' . $exception->getMessage());

            return [];
        }
    }

    private function renderSection(string $section): void
    {
        $user = $this->requireLogin();
        $servers = $this->sessionServers();
        $idsWithBot = $this->serverIdsWithBot($user['platform'], array_column($servers, 'id'));
        $managedServer = $this->managedServer($servers, $idsWithBot);

        // every section but the overview is about one server, so it waits until one is chosen
        if ($section !== 'overview' && $managedServer === null) {
            $this->redirect('/app');
        }

        $navigation = require dirname(__DIR__, 2) . '/config/panel-navigation.php';
        $platforms = require dirname(__DIR__, 2) . '/config/platforms.php';

        foreach ($navigation as $key => $item) {
            $navigation[$key]['locked'] = $key !== 'overview' && $managedServer === null;
        }

        $this->render('panel', [
            'pageTitle' => $this->lang()->text($navigation[$section]['name']),
            'navigation' => $navigation,
            'activeSection' => $section,
            'currentAccount' => $user,
            'currentPlatformName' => array_column($platforms, 'name', 'slug')[$user['platform']] ?? $user['platform'],
            'managedServer' => $managedServer,
            'servers' => $section === 'overview' ? $this->serverCards($servers, $idsWithBot, $managedServer) : [],
            'messageCheck' => $section === 'limits' ? $this->messageCheck($user['platform'], $managedServer['id']) : null,
        ]);
    }

    /**
     * The test box: the languages to choose from, with the first channel's language chosen at the start,
     * and the levels for the columns of the result.
     *
     * @return array{languages: list<string>, language: string, levels: list<string>, maxLength: int}
     */
    private function messageCheck(string $platform, string $serverId): array
    {
        try {
            $language = (new ChannelRepository((new Database())->connect()))->firstLanguage($platform, $serverId);
        } catch (RuntimeException $exception) {
            // the box still works, only with the page's language chosen
            error_log('Panel could not read the channel language: ' . $exception->getMessage());
            $language = null;
        }

        return [
            'languages' => (new MessageClassifier(self::TEST_MODEL))->languages(),
            'language' => $language ?? $this->lang()->code(),
            'levels' => MessageClassifier::LEVELS,
            'maxLength' => self::TEST_MAX_LENGTH,
        ];
    }
}
