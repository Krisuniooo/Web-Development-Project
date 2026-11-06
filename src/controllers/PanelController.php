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
        $user = $this->requireLogin();
        $this->renderSection($user, 'overview', ['servers' => $this->serverCards($user['platform'])]);
    }

    public function channels(): void
    {
        $this->renderSection($this->requireLogin(), 'channels');
    }

    public function models(): void
    {
        $this->renderSection($this->requireLogin(), 'models');
    }

    public function limits(): void
    {
        $this->renderSection($this->requireLogin(), 'limits');
    }

    public function logs(): void
    {
        $this->renderSection($this->requireLogin(), 'logs');
    }

    public function settings(): void
    {
        $this->renderSection($this->requireLogin(), 'settings');
    }

    /** @return array{platform: string, id: string, name: string} */
    private function requireLogin(): array
    {
        $user = $this->loggedInUser();

        if ($user === null) {
            $this->redirect('/login');
        }

        return $user;
    }

    /**
     * The servers saved at login, each with a link that invites the bot to that server
     * and whether the bot is already there.
     *
     * @return list<array{name: string, initials: string, iconUrl: ?string, inviteUrl: string, hasBot: bool}>
     */
    private function serverCards(string $platform): array
    {
        $servers = $_SESSION['servers'] ?? [];
        $servers = is_array($servers) ? $servers : [];
        $idsWithBot = $this->serverIdsWithBot($platform, array_column($servers, 'id'));
        $list = [];

        foreach ($servers as $server) {
            // shown instead of a missing icon: the first letters of the first two words, as on Discord
            $words = array_slice(preg_split('/\s+/u', trim($server['name']), -1, PREG_SPLIT_NO_EMPTY), 0, 2);

            $list[] = [
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

    private function renderSection(array $user, string $section, array $variables = []): void
    {
        $navigation = require dirname(__DIR__, 2) . '/config/panel-navigation.php';
        $platforms = require dirname(__DIR__, 2) . '/config/platforms.php';

        $this->render('panel', $variables + [
            'pageTitle' => $this->lang()->text($navigation[$section]['name']),
            'navigation' => $navigation,
            'activeSection' => $section,
            'currentAccount' => $user,
            'currentPlatformName' => array_column($platforms, 'name', 'slug')[$user['platform']] ?? $user['platform'],
        ]);
    }
}
