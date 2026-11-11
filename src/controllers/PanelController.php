<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database;
use App\Discord\DiscordBotClient;
use App\Discord\DiscordClient;
use App\Model\Channel;
use App\Model\ChannelRule;
use App\Model\ModerationEvent;
use App\Moderation\MessageClassifier;
use App\Repository\ChannelRepository;
use App\Repository\ModerationEventRepository;
use App\Repository\ServerRepository;
use PDO;
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
    // how long the channels from Discord are used before asking again: 2 times a minute of the 10 Discord allows
    private const VISIBLE_CHANNELS_SECONDS = 30;
    // the actions the bot performs now; timeout and ban come later
    private const RULE_ACTIONS = ['flag', 'delete'];
    // events on one page of the logs
    private const LOG_PAGE_SIZE = 50;
    // how long Discord's answer that the user may manage the server is trusted before asking again
    private const ACCESS_CHECK_SECONDS = 60;

    private ?PDO $pdo = null;

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

    /** Saves the language people write in on one channel of the chosen server. */
    public function setChannelLanguage(): void
    {
        [$user, $managedServer] = $this->requireManagedServer();

        $channelId = filter_var($_POST['channel'] ?? null, FILTER_VALIDATE_INT);
        $language = $_POST['language'] ?? null;
        $isSaved = false;

        try {
            $repository = new ChannelRepository($this->database());

            // only a channel of the chosen server, and only a language its model has questions in
            foreach ($repository->findForServer($user['platform'], $managedServer['id']) as $channel) {
                if ($channel->getId() === $channelId && in_array($language, $this->modelLanguages($channel->getModel()), true)) {
                    $repository->setLanguage($channelId, $language);
                    $isSaved = true;
                }
            }
        } catch (RuntimeException $exception) {
            error_log('Panel could not save the channel language: ' . $exception->getMessage());
        }

        if ($isSaved) {
            // the channel's own button shows it, so no notice
            $_SESSION['savedChannelId'] = $channelId;
        } else {
            $_SESSION['notice'] = ['text' => 'channels.save_error', 'isError' => true];
        }

        $this->redirect('/app/channels');
    }

    /** Pauses a channel of the chosen server, or lets the bot moderate it again; its settings stay. */
    public function setChannelStatus(): void
    {
        [$user, $managedServer] = $this->requireManagedServer();

        $channelId = filter_var($_POST['channel'] ?? null, FILTER_VALIDATE_INT);
        $status = $_POST['status'] ?? null;
        $isSaved = false;

        try {
            $repository = new ChannelRepository($this->database());

            // only a channel of the chosen server, and only a status the database knows
            foreach ($repository->findForServer($user['platform'], $managedServer['id']) as $channel) {
                if ($channel->getId() === $channelId && in_array($status, ChannelRepository::STATUSES, true)) {
                    $repository->setStatus($channelId, $status);
                    $isSaved = true;
                }
            }
        } catch (RuntimeException $exception) {
            error_log('Panel could not save the channel status: ' . $exception->getMessage());
        }

        $_SESSION['notice'] = $isSaved
            ? ['text' => $status === 'paused' ? 'channels.paused' : 'channels.resumed', 'isError' => false]
            : ['text' => 'channels.status_error', 'isError' => true];

        $this->redirect('/app/channels');
    }

    /** Connects a channel the bot can see to a free slot of the chosen server, with the default rules. */
    public function connectChannel(): void
    {
        [$user, $managedServer] = $this->requireManagedServer();

        $slot = filter_var($_POST['slot'] ?? null, FILTER_VALIDATE_INT);
        $platformChannelId = $_POST['channel'] ?? null;
        $language = $_POST['language'] ?? null;
        $defaults = $this->channelDefaults();
        $isConnected = false;

        try {
            $repository = new ChannelRepository($this->database());
            $connected = $repository->findForServer($user['platform'], $managedServer['id']);
            $takenSlots = array_map(fn(Channel $channel): int => $channel->getSlot(), $connected);

            // a free slot, and a language the model of a new channel has questions in
            if (
                in_array($slot, ChannelRepository::SLOTS, true)
                && !in_array($slot, $takenSlots, true)
                && in_array($language, $this->modelLanguages($defaults['model']), true)
            ) {
                // only a channel the bot can see and that is not connected yet; its name comes from Discord, not from the form
                foreach ($this->availableChannels($managedServer['id'], $connected) as $channel) {
                    if ($channel['id'] === $platformChannelId) {
                        $isConnected = $repository->add(
                            $user['platform'],
                            $managedServer['id'],
                            $slot,
                            $channel['id'],
                            $channel['name'],
                            $defaults['model'],
                            $language,
                            $defaults['rules'],
                        );
                    }
                }
            }
        } catch (RuntimeException $exception) {
            error_log('Panel could not connect a channel: ' . $exception->getMessage());
        }

        $_SESSION['notice'] = $isConnected
            ? ['text' => 'channels.connected', 'isError' => false]
            : ['text' => 'channels.connect_error', 'isError' => true];

        $this->redirect('/app/channels');
    }

    /** Moves a slot of the chosen server to another channel the bot can see; the language, model, rules and status stay. */
    public function changeChannel(): void
    {
        [$user, $managedServer] = $this->requireManagedServer();

        $slot = filter_var($_POST['slot'] ?? null, FILTER_VALIDATE_INT);
        $platformChannelId = $_POST['channel'] ?? null;
        $isChanged = false;

        try {
            $repository = new ChannelRepository($this->database());
            $connected = $repository->findForServer($user['platform'], $managedServer['id']);

            foreach ($connected as $current) {
                if ($current->getSlot() !== $slot) {
                    continue;
                }

                // only a channel the bot can see and that is not connected yet; its name comes from Discord, not from the form
                foreach ($this->availableChannels($managedServer['id'], $connected) as $channel) {
                    if ($channel['id'] === $platformChannelId) {
                        $repository->setPlatformChannel($current->getId(), $channel['id'], $channel['name']);
                        $isChanged = true;
                    }
                }
            }
        } catch (RuntimeException $exception) {
            error_log('Panel could not change a channel: ' . $exception->getMessage());
        }

        $_SESSION['notice'] = $isChanged
            ? ['text' => 'channels.changed', 'isError' => false]
            : ['text' => 'channels.change_error', 'isError' => true];

        $this->redirect('/app/channels');
    }

    /** Saves the level and the action of every category on one channel of the chosen server. */
    public function saveRules(): void
    {
        [$user, $managedServer] = $this->requireManagedServer();

        $channelId = filter_var($_POST['channel'] ?? null, FILTER_VALIDATE_INT);
        $posted = is_array($_POST['rules'] ?? null) ? $_POST['rules'] : [];
        $rules = [];

        // every category with a known level and an action the bot performs, or nothing is saved
        foreach ($this->ruleCategories() as $category) {
            $level = $posted[$category]['level'] ?? null;
            $action = $posted[$category]['action'] ?? null;

            if (!is_int($channelId) || !in_array($level, $this->ruleLevels(), true) || !in_array($action, self::RULE_ACTIONS, true)) {
                $rules = null;
                break;
            }

            $rules[] = new ChannelRule($channelId, $category, $level, $action);
        }

        $isSaved = false;

        try {
            $repository = new ChannelRepository($this->database());
            $ownIds = array_map(fn(Channel $channel): ?int => $channel->getId(), $repository->findForServer($user['platform'], $managedServer['id']));

            // only a channel of the chosen server
            if ($rules !== null && in_array($channelId, $ownIds, true)) {
                $repository->saveRules($rules);
                $isSaved = true;
            }
        } catch (RuntimeException $exception) {
            error_log('Panel could not save the rules: ' . $exception->getMessage());
        }

        if ($isSaved) {
            // the channel's own button shows it, so no notice
            $_SESSION['savedChannelId'] = $channelId;
        } else {
            $_SESSION['notice'] = ['text' => 'limits.save_error', 'isError' => true];
        }

        $this->redirect('/app/limits');
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

        // the same rule as the sections: a chosen server that still has the bot and that the user may still manage
        if ($this->managedServer($user) === null) {
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
        // nor is the database needed any more
        $this->pdo = null;

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
     * For the forms that change the chosen server: refuses a form from another site, and sends away
     * a visitor who is not logged in or has no server chosen.
     *
     * @return array{array{platform: string, id: string, name: string, avatarUrl: ?string}, array{id: string, name: string}}
     *     the user and the chosen server
     */
    private function requireManagedServer(): array
    {
        if (!$this->isSameOriginRequest()) {
            http_response_code(403);
            exit;
        }

        $user = $this->requireLogin();
        $managedServer = $this->managedServer($user);

        if ($managedServer === null) {
            $this->redirect('/app');
        }

        return [$user, $managedServer];
    }

    /**
     * The chosen server, checked again on every page: it must still be on the user's list, still have the bot,
     * and the user must still be allowed to manage it.
     *
     * @param array{platform: string, id: string} $user
     * @return array{id: string, name: string}|null
     */
    private function managedServer(array $user): ?array
    {
        $id = $_SESSION['managedServerId'] ?? null;

        foreach ($this->sessionServers() as $server) {
            if ($server['id'] === $id) {
                return $this->serverIdsWithBot($user['platform'], [$id]) === [$id] && $this->canStillManage($user['id'], $id)
                    ? ['id' => $server['id'], 'name' => $server['name']]
                    : null;
            }
        }

        return null;
    }

    /**
     * Asks Discord whether the user may manage the server now, and trusts a yes for a while.
     * Someone who left, was banned or lost the permission also loses the server from their list.
     */
    private function canStillManage(string $userId, string $serverId): bool
    {
        $saved = $_SESSION['accessCheck'] ?? null;

        if (
            is_array($saved)
            && ($saved['serverId'] ?? null) === $serverId
            && is_int($saved['time'] ?? null) && $saved['time'] > time() - self::ACCESS_CHECK_SECONDS
        ) {
            return true;
        }

        try {
            $canManage = $this->discordBot()->canManageServer($serverId, $userId);
        } catch (RuntimeException $exception) {
            // without an answer nobody gets in, but the server stays on the list for when Discord answers again
            error_log('Panel could not check the access to a server: ' . $exception->getMessage());

            return false;
        }

        if ($canManage) {
            $_SESSION['accessCheck'] = ['serverId' => $serverId, 'time' => time()];

            return true;
        }

        unset($_SESSION['managedServerId'], $_SESSION['accessCheck'], $_SESSION['visibleChannels']);
        $_SESSION['servers'] = array_values(array_filter(
            $this->sessionServers(),
            fn(array $server): bool => $server['id'] !== $serverId,
        ));

        return false;
    }

    /** One database connection for the whole request, opened when first needed. */
    private function database(): PDO
    {
        return $this->pdo ??= (new Database())->connect();
    }

    private function discordBot(): DiscordBotClient
    {
        return new DiscordBotClient(
            trim((string) getenv('DISCORD_BOT_TOKEN')),
            getenv('DISCORD_API_URL') ?: DiscordClient::API_URL,
        );
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
            return (new ServerRepository($this->database()))->findActiveIds($platform, $serverIds);
        } catch (RuntimeException $exception) {
            // the servers come from the session, so the list still shows, only without the bot status
            error_log('Panel could not check which servers have the bot: ' . $exception->getMessage());

            return [];
        }
    }

    private function renderSection(string $section): void
    {
        $user = $this->requireLogin();
        $managedServer = $this->managedServer($user);
        // read after the check above, which takes a server the user can no longer manage off the list
        $servers = $this->sessionServers();

        // every section but the overview is about one server, so it waits until one is chosen
        if ($section !== 'overview' && $managedServer === null) {
            $this->redirect('/app');
        }

        $navigation = require dirname(__DIR__, 2) . '/config/panel-navigation.php';
        $platforms = require dirname(__DIR__, 2) . '/config/platforms.php';

        foreach ($navigation as $key => $item) {
            $navigation[$key]['locked'] = $key !== 'overview' && $managedServer === null;
        }

        // what the last form did, shown once
        $notice = $_SESSION['notice'] ?? null;
        $savedChannelId = $_SESSION['savedChannelId'] ?? null;
        unset($_SESSION['notice'], $_SESSION['savedChannelId']);

        $channelSlots = $section === 'channels' ? $this->channelSlots($user['platform'], $managedServer['id']) : null;
        // the slot whose channel is being changed, after "Change channel"; only a slot with a channel counts
        $changingSlot = filter_var($_GET['change'] ?? null, FILTER_VALIDATE_INT);
        $changingSlot = is_int($changingSlot) && isset($channelSlots[$changingSlot]) ? $changingSlot : null;

        $this->render('panel', [
            'pageTitle' => $this->lang()->text($navigation[$section]['name']),
            'navigation' => $navigation,
            'activeSection' => $section,
            'currentAccount' => $user,
            'currentPlatformName' => array_column($platforms, 'name', 'slug')[$user['platform']] ?? $user['platform'],
            'managedServer' => $managedServer,
            'notice' => is_string($notice['text'] ?? null) ? ['text' => $notice['text'], 'isError' => ($notice['isError'] ?? false) === true] : null,
            'servers' => $section === 'overview'
                ? $this->serverCards($servers, $this->serverIdsWithBot($user['platform'], array_column($servers, 'id')), $managedServer)
                : [],
            'channelSlots' => $channelSlots,
            'savedChannelId' => is_int($savedChannelId) ? $savedChannelId : null,
            'changingSlot' => $changingSlot,
            // Discord is asked only when there is a channel to choose: for a free slot or a change
            'channelChoice' => $channelSlots !== null && (in_array(null, $channelSlots, true) || $changingSlot !== null)
                ? $this->channelChoice($managedServer['id'], $channelSlots)
                : null,
            'limits' => $section === 'limits' ? $this->channelLimits($user['platform'], $managedServer['id']) : null,
            'log' => $section === 'logs' ? $this->moderationLog($user['platform'], $managedServer['id']) : null,
            'messageCheck' => $section === 'limits' ? $this->messageCheck($user['platform'], $managedServer['id']) : null,
        ]);
    }

    /**
     * Each channel of the chosen server with its rule for every category, or null for the channels when the database
     * does not answer; and the levels and actions to choose from.
     *
     * @return array{channels: list<array{channel: Channel, rules: array<string, ChannelRule>}>|null, levels: list<string>, actions: list<string>}
     */
    private function channelLimits(string $platform, string $serverId): array
    {
        $channels = [];

        try {
            $repository = new ChannelRepository($this->database());

            foreach ($repository->findForServer($platform, $serverId) as $channel) {
                $saved = $repository->findRules($channel->getId());
                $rules = [];

                foreach ($this->ruleCategories() as $category) {
                    // a category the channel has no rule for yet shows as off; saving adds the rule
                    $rules[$category] = $saved[$category] ?? new ChannelRule($channel->getId(), $category, 'off', 'delete');
                }

                $channels[] = ['channel' => $channel, 'rules' => $rules];
            }
        } catch (RuntimeException $exception) {
            error_log('Panel could not read the rules: ' . $exception->getMessage());
            $channels = null;
        }

        return ['channels' => $channels, 'levels' => $this->ruleLevels(), 'actions' => self::RULE_ACTIONS];
    }

    /**
     * One page of what the bot did on the chosen server, newest first, or null for the events when the database
     * does not answer. ?before=<id> shows the events older than that one, and ?channel=, ?category= and ?action=
     * narrow the list; a value that is not among the choices is ignored.
     *
     * @return array{
     *     events: list<ModerationEvent>|null,
     *     isNewest: bool,
     *     olderBefore: ?int,
     *     filters: array<string, string>,
     *     choices: array{channel: list<array{id: string, name: string}>, category: list<string>, action: list<string>},
     * }
     *     olderBefore: the id to ask for the next older page with, or null when there is none;
     *     filters: the chosen ones by name, as they go back into the links
     */
    private function moderationLog(string $platform, string $serverId): array
    {
        $before = filter_var($_GET['before'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $before = is_int($before) ? $before : null;
        $choices = ['channel' => [], 'category' => $this->ruleCategories(), 'action' => self::RULE_ACTIONS];
        $filters = [];
        $events = null;

        try {
            // the channels connected now; an event from a channel that was replaced shows only without this filter
            foreach ((new ChannelRepository($this->database()))->findForServer($platform, $serverId) as $channel) {
                $choices['channel'][] = ['id' => $channel->getPlatformChannelId(), 'name' => $channel->getName()];
            }

            $allowed = ['channel' => array_column($choices['channel'], 'id')] + $choices;

            foreach ($allowed as $name => $values) {
                if (in_array($_GET[$name] ?? null, $values, true)) {
                    $filters[$name] = $_GET[$name];
                }
            }

            // one more than a page tells whether there are older events
            $events = (new ModerationEventRepository($this->database()))->findForServer(
                $platform,
                $serverId,
                $before,
                self::LOG_PAGE_SIZE + 1,
                $filters['channel'] ?? null,
                $filters['category'] ?? null,
                $filters['action'] ?? null,
            );
        } catch (RuntimeException $exception) {
            error_log('Panel could not read the events: ' . $exception->getMessage());
        }

        $page = $events === null ? null : array_slice($events, 0, self::LOG_PAGE_SIZE);

        return [
            'events' => $page,
            'isNewest' => $before === null,
            'olderBefore' => $events !== null && count($events) > self::LOG_PAGE_SIZE ? end($page)->getId() : null,
            'filters' => $filters,
            'choices' => $choices,
        ];
    }

    /** @return list<string> the categories with a rule: those every new channel gets */
    private function ruleCategories(): array
    {
        return array_keys($this->channelDefaults()['rules']);
    }

    /** @return list<string> off, and then from the fewest blocked messages to the most */
    private function ruleLevels(): array
    {
        return ['off', ...MessageClassifier::LEVELS];
    }

    /**
     * Each place for a channel, with the channel there and the languages its model can use, or null while it is free.
     * Null instead of the list when the database does not answer.
     *
     * @return array<int, array{channel: Channel, languages: list<string>}|null>|null by slot
     */
    private function channelSlots(string $platform, string $serverId): ?array
    {
        try {
            $channels = (new ChannelRepository($this->database()))->findForServer($platform, $serverId);
        } catch (RuntimeException $exception) {
            error_log('Panel could not read the channels: ' . $exception->getMessage());

            return null;
        }

        $slots = array_fill_keys(ChannelRepository::SLOTS, null);

        foreach ($channels as $channel) {
            $slots[$channel->getSlot()] = ['channel' => $channel, 'languages' => $this->modelLanguages($channel->getModel())];
        }

        return $slots;
    }

    /**
     * What a free slot or a change offers: the channels to choose from, or null when Discord does not answer,
     * the languages of the default model, and the language chosen at the start: that of a connected channel, or else the page's.
     *
     * @param array<int, array{channel: Channel, languages: list<string>}|null> $slots
     * @return array{channels: list<array{id: string, name: string}>|null, languages: list<string>, language: string}
     */
    private function channelChoice(string $serverId, array $slots): array
    {
        $connected = array_column(array_filter($slots), 'channel');
        $languages = $this->modelLanguages($this->channelDefaults()['model']);
        $language = $connected === [] ? $this->lang()->code() : $connected[0]->getLanguage();

        try {
            $channels = $this->availableChannels($serverId, $connected);
        } catch (RuntimeException $exception) {
            error_log('Panel could not read the channels from Discord: ' . $exception->getMessage());
            $channels = null;
        }

        return [
            'channels' => $channels,
            'languages' => $languages,
            'language' => in_array($language, $languages, true) ? $language : ($languages[0] ?? ''),
        ];
    }

    /**
     * The server's text channels the bot can see, without those already connected.
     *
     * @param list<Channel> $connected
     * @return list<array{id: string, name: string}>
     */
    private function availableChannels(string $serverId, array $connected): array
    {
        $connectedIds = array_map(fn(Channel $channel): string => $channel->getPlatformChannelId(), $connected);

        return array_values(array_filter(
            $this->visibleChannels($serverId),
            fn(array $channel): bool => !in_array($channel['id'], $connectedIds, true),
        ));
    }

    /**
     * The server's text channels the bot can see. Discord lets the bot ask only 10 times a minute for each server,
     * so the list is kept in the session for a while.
     *
     * @return list<array{id: string, name: string}>
     */
    private function visibleChannels(string $serverId): array
    {
        $saved = $_SESSION['visibleChannels'] ?? null;

        if (
            is_array($saved)
            && ($saved['serverId'] ?? null) === $serverId
            && is_int($saved['time'] ?? null) && $saved['time'] > time() - self::VISIBLE_CHANNELS_SECONDS
            && is_array($saved['channels'] ?? null)
        ) {
            return $saved['channels'];
        }

        $channels = $this->discordBot()->fetchVisibleTextChannels($serverId);
        $_SESSION['visibleChannels'] = ['serverId' => $serverId, 'time' => time(), 'channels' => $channels];

        return $channels;
    }

    /** @return array{model: string, rules: array<string, array{level: string, action: string}>} the settings of a new channel */
    private function channelDefaults(): array
    {
        return require dirname(__DIR__, 2) . '/config/channel-defaults.php';
    }

    /** @return list<string> the languages a model has questions in; none for a model without settings in config/moderation.php */
    private function modelLanguages(string $model): array
    {
        try {
            return (new MessageClassifier($model))->languages();
        } catch (RuntimeException) {
            return [];
        }
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
            $language = (new ChannelRepository($this->database()))->firstLanguage($platform, $serverId);
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
