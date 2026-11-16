<?php

declare(strict_types=1);

namespace App\Controller;

use App\Database;
use App\Discord\DiscordBotClient;
use App\Discord\DiscordClient;
use App\Discord\DiscordPermissions;
use App\Model\Channel;
use App\Model\ChannelRule;
use App\Model\ModerationEvent;
use App\Moderation\MessageClassifier;
use App\Repository\ChannelRepository;
use App\Repository\ModerationEventRepository;
use App\Repository\ServiceStatusRepository;
use App\Service\ServerAccess;
use PDO;
use RuntimeException;

final class PanelController extends AppController
{
    // asked for all at once, so turning on timeouts or bans later needs no new invite
    private const DISCORD_BOT_PERMISSIONS = DiscordPermissions::VIEW_CHANNEL
        | DiscordPermissions::MANAGE_MESSAGES
        | DiscordPermissions::MODERATE_MEMBERS
        | DiscordPermissions::BAN_MEMBERS;
    // the longest message Discord allows without Nitro
    private const TEST_MAX_LENGTH = 2000;
    // per login, because the bot uses the same model
    private const TEST_CHECKS_LIMIT = 10;
    private const TEST_LIMIT_SECONDS = 60;
    // the actions the bot performs now; timeout and ban come later
    private const RULE_ACTIONS = [ChannelRule::ACTION_FLAG, ChannelRule::ACTION_DELETE];
    private const LOG_PAGE_SIZE = 50;
    // the bot reports about every half a minute; after this long without a report it counts as not connected
    private const BOT_SILENCE_SECONDS = 120;
    // in days; the first is the default
    private const OVERVIEW_PERIODS = [7, 30];
    private const TOP_AUTHORS = 5;
    // as many as the is-tone-* classes in css/panel.css
    private const AVATAR_TONES = 6;
    // how long a picture from Discord stays in the session
    private const AVATAR_SECONDS = 3600;
    // as long as authors are kept
    private const AUTHOR_HISTORY_DAYS = ModerationEventRepository::KEEP_CONTENT_DAYS;

    private ?PDO $pdo = null;
    private ?ServerAccess $access = null;

    public function servers(): void
    {
        $this->renderSection('servers');
    }

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

    /** One event of the logs on its own page, to link to; only an event of the chosen server is shown. */
    public function logEvent(int $id): void
    {
        $user = $this->requireLogin();
        $managedServer = $this->access()->managedServer($user);

        if ($managedServer === null) {
            $this->redirect('/app/servers');
        }

        // the ids start at 1, so a smaller one is not looked up
        $found = $id < 1 ? null : (new ModerationEventRepository($this->database()))->findWithServer($id);

        if ($found === null) {
            $this->notFound();
            return;
        }

        // an event of someone else's server, or of another server of this user, which has to be chosen first
        if ($found['platform'] !== $user['platform'] || $found['platformServerId'] !== $managedServer['id']) {
            $this->forbidden();
            return;
        }

        $this->renderSection('logs', [
            'event' => $found['event'],
            'author' => $this->eventAuthor($user['platform'], $managedServer['id'], $found['event']),
            'historyDays' => self::AUTHOR_HISTORY_DAYS,
        ]);
    }

    /** Chooses the server the other sections are about. */
    public function manage(): void
    {
        if (!$this->isSameOriginRequest()) {
            http_response_code(403);
            return;
        }

        $this->access()->choose($this->requireLogin(), $_POST['server'] ?? null);

        $this->redirect('/app');
    }

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
                if ($channel->getId() === $channelId && in_array($status, Channel::STATUSES, true)) {
                    $repository->setStatus($channelId, $status);
                    $isSaved = true;
                }
            }
        } catch (RuntimeException $exception) {
            error_log('Panel could not save the channel status: ' . $exception->getMessage());
        }

        $_SESSION['notice'] = $isSaved
            ? ['text' => $status === Channel::STATUS_PAUSED ? 'channels.paused' : 'channels.resumed', 'isError' => false]
            : ['text' => 'channels.status_error', 'isError' => true];

        $this->redirect('/app/channels');
    }

    /** Saves the model that checks one channel of the chosen server; its language and rules stay. */
    public function setChannelModel(): void
    {
        [$user, $managedServer] = $this->requireManagedServer();

        $channelId = filter_var($_POST['channel'] ?? null, FILTER_VALIDATE_INT);
        $model = $_POST['model'] ?? null;
        $isSaved = false;

        try {
            $repository = new ChannelRepository($this->database());

            // only a channel of the chosen server, a model the bot has, and one with questions in the channel's language
            foreach ($repository->findForServer($user['platform'], $managedServer['id']) as $channel) {
                if (
                    $channel->getId() === $channelId
                    && in_array($model, $this->availableModels(), true)
                    && in_array($channel->getLanguage(), $this->modelLanguages($model), true)
                ) {
                    $repository->setModel($channelId, $model);
                    $isSaved = true;
                }
            }
        } catch (RuntimeException $exception) {
            error_log('Panel could not save the channel model: ' . $exception->getMessage());
        }

        if ($isSaved) {
            // the channel's own button shows it, so no notice
            $_SESSION['savedChannelId'] = $channelId;
        } else {
            $_SESSION['notice'] = ['text' => 'models.save_error', 'isError' => true];
        }

        $this->redirect('/app/models');
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
                foreach ($this->access()->availableChannels($managedServer['id'], $connected) as $channel) {
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
                foreach ($this->access()->availableChannels($managedServer['id'], $connected) as $channel) {
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
            $ownIds = array_map(fn(Channel $channel): int => $channel->getId(), $repository->findForServer($user['platform'], $managedServer['id']));

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

        if (!$this->testClassifier()->isReady()) {
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
        if ($this->access()->managedServer($user) === null) {
            $this->json(['error' => $lang->text('check.error.server')], 403);
            return;
        }

        $classifier = $this->testClassifier();
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

        // the checks within the limit's time; a request turned down above does not count
        $now = time();
        $times = is_array($_SESSION['messageChecks'] ?? null) ? $_SESSION['messageChecks'] : [];
        $recent = array_values(array_filter($times, fn(mixed $time): bool => is_int($time) && $time > $now - self::TEST_LIMIT_SECONDS));

        if (count($recent) >= self::TEST_CHECKS_LIMIT) {
            header('Retry-After: ' . ($recent[0] + self::TEST_LIMIT_SECONDS - $now));
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

        $brokenByLevel = $classifier->brokenRulesByLevel($scores, $language);
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
        $managedServer = $this->access()->managedServer($user);

        if ($managedServer === null) {
            $this->redirect('/app/servers');
        }

        return [$user, $managedServer];
    }

    /** One database connection for the whole request, opened when first needed. */
    private function database(): PDO
    {
        return $this->pdo ??= (new Database())->connect();
    }

    /** Which servers the user may manage, and what the bot sees on them; shares the request's connection. */
    private function access(): ServerAccess
    {
        return $this->access ??= new ServerAccess($this->database(...));
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
            $list[] = [
                'id' => $server['id'],
                'name' => $server['name'],
                // shown instead of a missing icon
                'initials' => $this->initials($server['name']),
                'iconUrl' => DiscordClient::serverIconUrl($server['id'], $server['icon']),
                'inviteUrl' => DiscordClient::AUTHORIZE_URL . '?' . http_build_query([
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

    /** @param array{event: ModerationEvent, author: ?array, historyDays: int}|null $logEvent the one event the logs show instead of the list */
    private function renderSection(string $section, ?array $logEvent = null): void
    {
        $user = $this->requireLogin();
        $managedServer = $this->access()->managedServer($user);
        // read after the check above, which takes a server the user can no longer manage off the list
        $servers = $this->access()->servers();

        // every section but the list of servers is about one server, so it waits until one is chosen there
        if ($section !== 'servers' && $managedServer === null) {
            $this->redirect('/app/servers');
        }

        $navigation = require dirname(__DIR__, 2) . '/config/panel-navigation.php';
        $platforms = require dirname(__DIR__, 2) . '/config/platforms.php';

        foreach ($navigation as $key => $item) {
            $navigation[$key]['locked'] = $key !== 'servers' && $managedServer === null;
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
            'pageTitle' => $logEvent === null
                ? $this->lang()->text($navigation[$section]['name'])
                : sprintf($this->lang()->text('logs.event.title'), $logEvent['event']->getId()),
            'navigation' => $navigation,
            'activeSection' => $section,
            'currentAccount' => $user,
            'currentPlatformName' => array_column($platforms, 'name', 'slug')[$user['platform']] ?? $user['platform'],
            'managedServer' => $managedServer,
            'notice' => is_string($notice['text'] ?? null) ? ['text' => $notice['text'], 'isError' => ($notice['isError'] ?? false) === true] : null,
            'servers' => $section === 'servers'
                ? $this->serverCards($servers, $this->access()->idsWithBot($user['platform'], array_column($servers, 'id')), $managedServer)
                : [],
            'overview' => $section === 'overview' ? $this->overviewData($user['platform'], $managedServer['id']) : null,
            'modelChannels' => $section === 'models' ? $this->modelChannels($user['platform'], $managedServer['id']) : null,
            'channelSlots' => $channelSlots,
            'savedChannelId' => is_int($savedChannelId) ? $savedChannelId : null,
            'changingSlot' => $changingSlot,
            // Discord is asked only when there is a channel to choose: for a free slot or a change
            'channelChoice' => $channelSlots !== null && (in_array(null, $channelSlots, true) || $changingSlot !== null)
                ? $this->channelChoice($managedServer['id'], $channelSlots)
                : null,
            'limits' => $section === 'limits' ? $this->channelLimits($user['platform'], $managedServer['id']) : null,
            'log' => $section === 'logs' && $logEvent === null ? $this->moderationLog($user['platform'], $managedServer['id']) : null,
            'logEvent' => $logEvent,
            'messageCheck' => $section === 'limits' ? $this->messageCheck($user['platform'], $managedServer['id']) : null,
        ]);
    }

    /**
     * Each channel with its model and the models to choose from; null when the database does not answer.
     *
     * @return list<array{
     *     channel: Channel,
     *     label: string,
     *     size: ?string,
     *     languages: list<string>,
     *     choices: list<array{slug: string, label: string, isForLanguage: bool}>,
     * }>|null
     */
    private function modelChannels(string $platform, string $serverId): ?array
    {
        try {
            $channels = (new ChannelRepository($this->database()))->findForServer($platform, $serverId);
        } catch (RuntimeException $exception) {
            error_log('Panel could not read the channels: ' . $exception->getMessage());

            return null;
        }

        $offers = array_column(require dirname(__DIR__, 2) . '/config/models.php', null, 'slug');

        return array_map(fn(Channel $channel): array => [
            'channel' => $channel,
            // a model the home page does not list still shows, under the name the database has
            'label' => $offers[$channel->getModel()]['label'] ?? $channel->getModel(),
            'size' => $offers[$channel->getModel()]['size'] ?? null,
            'languages' => $this->modelLanguages($channel->getModel()),
            'choices' => array_map(fn(string $slug): array => [
                'slug' => $slug,
                'label' => $offers[$slug]['label'] ?? $slug,
                'isForLanguage' => in_array($channel->getLanguage(), $this->modelLanguages($slug), true),
            ], $this->availableModels()),
        ], $channels);
    }

    /**
     * Adds each author's picture from Discord, or initials for when there is none. The bot stores no pictures,
     * so they are kept in the session for a while, an unknown user too. After any other failure the rest
     * is not asked for, so a Discord that does not answer delays the page only once.
     *
     * @param list<array{id: string, name: string, events: int, category: string}> $authors
     * @return list<array{id: string, name: string, events: int, category: string, avatarUrl: ?string, initials: string, tone: int}>
     */
    private function withAvatars(array $authors): array
    {
        $saved = is_array($_SESSION['authorAvatars'] ?? null) ? $_SESSION['authorAvatars'] : [];
        // only the pictures still fresh stay, so the session does not keep growing
        $saved = array_filter($saved, fn(mixed $entry): bool => is_int($entry['time'] ?? null) && $entry['time'] > time() - self::AVATAR_SECONDS);
        $isDiscordDown = false;

        foreach ($authors as $index => $author) {
            if (!isset($saved[$author['id']]) && !$isDiscordDown) {
                try {
                    $url = DiscordBotClient::fromEnvironment()->fetchAvatarUrl($author['id']);
                } catch (RuntimeException $exception) {
                    error_log('Panel could not read the picture of an author: ' . $exception->getMessage());
                    $url = null;
                    // a user Discord does not know is an answer; anything else means Discord may not answer the rest either
                    $isDiscordDown = $exception->getCode() !== 404;
                }

                if (!$isDiscordDown) {
                    $saved[$author['id']] = ['url' => $url, 'time' => time()];
                }
            }

            $url = $saved[$author['id']]['url'] ?? null;
            $authors[$index] += [
                'avatarUrl' => is_string($url) ? $url : null,
                'initials' => $this->initials($author['name']),
                // from the ID, so the same person keeps the same colour from page to page
                'tone' => crc32($author['id']) % self::AVATAR_TONES,
            ];
        }

        $_SESSION['authorAvatars'] = $saved;

        return $authors;
    }

    /**
     * The author of an event with their picture, a link to their profile and how often they broke the rules on this
     * server lately; null once bin/cleanup.php has removed the author from the event.
     *
     * @return array{id: string, name: string, events: int, category: string, avatarUrl: ?string, initials: string, tone: int, profileUrl: ?string}|null
     *     events and category: in the last AUTHOR_HISTORY_DAYS days, this event included
     */
    private function eventAuthor(string $platform, string $serverId, ModerationEvent $event): ?array
    {
        $id = $event->getAuthorPlatformId();

        if ($id === null) {
            return null;
        }

        $history = (new ModerationEventRepository($this->database()))
            ->topAuthors($platform, $serverId, self::AUTHOR_HISTORY_DAYS, 1, $id)[0] ?? null;
        $platforms = require dirname(__DIR__, 2) . '/config/platforms.php';
        $profileUrl = array_column($platforms, 'profileUrl', 'slug')[$platform] ?? null;

        return $this->withAvatars([[
            'id' => $id,
            'name' => (string) $event->getAuthorName(),
            // an event older than the period is not counted in it
            'events' => $history['events'] ?? 0,
            'category' => $history['category'] ?? $event->getCategory(),
        ]])[0] + ['profileUrl' => $profileUrl === null ? null : sprintf($profileUrl, $id)];
    }

    /** The first letters of the first two words of a name, as Discord shows a server without an icon. */
    private function initials(string $name): string
    {
        $words = array_slice(preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY), 0, 2);

        return mb_strtoupper(implode('', array_map(fn(string $word): string => mb_substr($word, 0, 1), $words)));
    }

    /** @return list<string> the models the bot can ask: those with settings in config/moderation.php */
    private function availableModels(): array
    {
        return array_keys(require dirname(__DIR__, 2) . '/config/moderation.php');
    }

    /**
     * Whether the bot and the models work now, and what the bot did in the period chosen by ?days=.
     * An empty log alone cannot tell a clean chat from a stopped bot, so checked messages are counted next to actions.
     *
     * @return array{
     *     isBotConnected: ?bool,
     *     models: list<array{label: string, isReady: bool}>,
     *     days: int,
     *     periods: list<int>,
     *     stats: array{
     *         checked: int,
     *         acted: int,
     *         failed: int,
     *         channels: list<array{channel: Channel, model: string, checked: int, acted: int}>,
     *         categories: array<string, int>,
     *         authors: list<array{id: string, name: string, events: int, category: string, avatarUrl: ?string, initials: string, tone: int}>,
     *     }|null,
     * }
     *     isBotConnected and stats: null when the database does not answer;
     *     models: those the channels use, or every model while the server has no channel
     */
    private function overviewData(string $platform, string $serverId): array
    {
        $days = filter_var($_GET['days'] ?? null, FILTER_VALIDATE_INT);
        $days = in_array($days, self::OVERVIEW_PERIODS, true) ? $days : self::OVERVIEW_PERIODS[0];
        $offers = array_column(require dirname(__DIR__, 2) . '/config/models.php', null, 'slug');
        $models = $this->availableModels();
        $isBotConnected = null;
        $stats = null;

        try {
            $pdo = $this->database();
            $seconds = (new ServiceStatusRepository($pdo))->secondsSinceSeen(ServiceStatusRepository::DISCORD_BOT);
            $isBotConnected = $seconds !== null && $seconds <= self::BOT_SILENCE_SECONDS;

            $channelRepository = new ChannelRepository($pdo);
            $channels = $channelRepository->findForServer($platform, $serverId);
            $checks = $channelRepository->countChecksByChannel($platform, $serverId, $days);
            $events = new ModerationEventRepository($pdo);
            $summary = $events->summarize($platform, $serverId, $days);

            // a server without channels has no model of its own yet, so it sees them all
            if ($channels !== []) {
                $models = array_values(array_intersect($models, array_map(fn(Channel $channel): string => $channel->getModel(), $channels)));
            }

            $stats = [
                // the checks of channels that were replaced since are gone with them, so the total is that of the channels now
                'checked' => array_sum($checks),
                'acted' => $summary['acted'],
                'failed' => $summary['failed'],
                'channels' => array_map(fn(Channel $channel): array => [
                    'channel' => $channel,
                    'model' => $offers[$channel->getModel()]['label'] ?? $channel->getModel(),
                    'checked' => $checks[$channel->getId()] ?? 0,
                    'acted' => $summary['actedByChannel'][$channel->getId()] ?? 0,
                ], $channels),
                // every rule, even one nobody broke, so the list always looks the same
                'categories' => array_map(
                    fn(string $category): int => $summary['byCategory'][$category] ?? 0,
                    array_combine($this->ruleCategories(), $this->ruleCategories()),
                ),
                'authors' => $this->withAvatars($events->topAuthors($platform, $serverId, $days, self::TOP_AUTHORS)),
            ];
        } catch (RuntimeException $exception) {
            error_log('Panel could not read the overview: ' . $exception->getMessage());
        }

        return [
            'isBotConnected' => $isBotConnected,
            'models' => array_map(fn(string $model): array => [
                'label' => $offers[$model]['label'] ?? $model,
                'isReady' => (new MessageClassifier($model))->isReady(),
            ], $models),
            'days' => $days,
            'periods' => self::OVERVIEW_PERIODS,
            'stats' => $stats,
        ];
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
                    $rules[$category] = $saved[$category]
                        ?? new ChannelRule($channel->getId(), $category, MessageClassifier::LEVEL_OFF, ChannelRule::ACTION_DELETE);
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
        return [MessageClassifier::LEVEL_OFF, ...MessageClassifier::LEVELS];
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
            $channels = $this->access()->availableChannels($serverId, $connected);
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

    /** @return array{model: string, rules: array<string, array{level: string, action: string}>} the settings of a new channel */
    private function channelDefaults(): array
    {
        return require dirname(__DIR__, 2) . '/config/channel-defaults.php';
    }

    /** The model of the test box: the one new channels get, as bin/check-message.php uses too. */
    private function testClassifier(): MessageClassifier
    {
        return new MessageClassifier($this->channelDefaults()['model']);
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
            'languages' => $this->testClassifier()->languages(),
            'language' => $language ?? $this->lang()->code(),
            'levels' => MessageClassifier::LEVELS,
            'maxLength' => self::TEST_MAX_LENGTH,
        ];
    }
}
