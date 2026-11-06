<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database;
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
        ]);
    }
}
