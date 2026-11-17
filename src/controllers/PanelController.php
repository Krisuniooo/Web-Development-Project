<?php

declare(strict_types=1);

namespace App\Controller;

use App\Config;
use App\Discord\DiscordBotClient;
use App\Discord\DiscordClient;
use App\Discord\DiscordPermissions;
use App\Model\Channel;
use App\Model\ChannelRule;
use App\Model\ModerationEvent;
use App\Moderation\MessageClassifier;
use App\Repository\BotAccountRepository;
use App\Repository\ChannelRepository;
use App\Repository\ModerationEventRepository;
use App\Repository\ServerRepository;
use App\Repository\ServiceStatusRepository;
use RuntimeException;

/**
 * The pages of the panel. Each action prepares what its own section shows and hands it to renderSection(),
 * which adds what every section has in common.
 */
final class PanelController extends AbstractPanelController
{
    // asked for all at once, so turning on timeouts or bans later needs no new invite
    private const DISCORD_BOT_PERMISSIONS = DiscordPermissions::VIEW_CHANNEL
        | DiscordPermissions::MANAGE_MESSAGES
        | DiscordPermissions::MODERATE_MEMBERS
        | DiscordPermissions::BAN_MEMBERS;
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

    /** The only section open before a server is chosen. */
    public function servers(): void
    {
        $user = $this->requireLogin();
        $managedServer = $this->access()->managedServer($user);
        // read after the check above, which takes a server the user can no longer manage off the list
        $servers = $this->access()->servers();
        $idsWithBot = $this->access()->idsWithBot($user['platform'], array_column($servers, 'id'));

        $this->renderSection('servers', $user, $managedServer, [
            'servers' => $this->serverCards($user['platform'], $servers, $idsWithBot, $managedServer),
            // logging in again reads the list anew
            'refreshPath' => $this->platform($user['platform'])['authPath'],
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

    /**
     * Connects a server on a platform where no bot is invited, such as a Twitch channel, and chooses it.
     * The server is its own only chat, so it gets that channel at once, with the default settings.
     * One connected before keeps the settings it had.
     */
    public function connect(): void
    {
        if (!$this->isSameOriginRequest()) {
            http_response_code(403);
            return;
        }

        $user = $this->requireLogin();
        $platform = $user['platform'];
        // only a server on the user's list, and only after the platform said again that they may manage it
        $server = $this->platform($platform)['connect'] === 'panel'
            ? $this->access()->confirmedServer($user, $_POST['server'] ?? null)
            : null;
        $isConnected = false;

        if ($server !== null) {
            $defaults = $this->channelDefaults();
            // Polish only when the owner set it on the platform and the model has questions in it, as for a Discord server
            $language = $this->access()->serverLanguage($user, $server['id']) === 'pl'
                && in_array('pl', $this->modelLanguages($defaults['model']), true) ? 'pl' : 'en';

            try {
                (new ServerRepository($this->database()))->saveActive($platform, $server['id'], $server['name']);
                (new ChannelRepository($this->database()))->createFirst(
                    $platform,
                    $server['id'],
                    $server['id'],
                    $server['name'],
                    $defaults['model'],
                    $language,
                    $defaults['rules'],
                );
                $isConnected = true;
            } catch (RuntimeException $exception) {
                error_log('Panel could not connect a server: ' . $exception->getMessage());
            }
        }

        if (!$isConnected) {
            $_SESSION['notice'] = ['text' => 'servers.connect_error', 'isError' => true];
            $this->redirect('/app/servers');
        }

        $this->access()->choose($user, $server['id']);

        $this->redirect('/app');
    }

    public function overview(): void
    {
        [$user, $managedServer] = $this->requireManagedServer();

        $this->renderSection('overview', $user, $managedServer, [
            'overview' => $this->overviewData($user['platform'], $managedServer['id']),
        ]);
    }

    public function channels(): void
    {
        [$user, $managedServer] = $this->requireManagedServer();

        $channelSlots = $this->channelSlots($user['platform'], $managedServer['id']);
        // a server that is its own only chat has no other channel to move the settings to
        $canChange = $channelSlots !== null && count($channelSlots) > 1;
        // the slot whose channel is being changed, after "Change channel"; only a slot with a channel counts
        $changingSlot = filter_var($_GET['change'] ?? null, FILTER_VALIDATE_INT);
        $changingSlot = $canChange && is_int($changingSlot) && isset($channelSlots[$changingSlot]) ? $changingSlot : null;

        $this->renderSection('channels', $user, $managedServer, [
            'channelSlots' => $channelSlots,
            'canChange' => $canChange,
            'botName' => $this->botName($user['platform']),
            'changingSlot' => $changingSlot,
            // Discord is asked only when there is a channel to choose: for a free slot or a change
            'channelChoice' => $channelSlots !== null && (in_array(null, $channelSlots, true) || $changingSlot !== null)
                ? $this->channelChoice($managedServer['id'], $channelSlots)
                : null,
        ]);
    }

    public function models(): void
    {
        [$user, $managedServer] = $this->requireManagedServer();

        $this->renderSection('models', $user, $managedServer, [
            'modelChannels' => $this->modelChannels($user['platform'], $managedServer['id']),
        ]);
    }

    public function limits(): void
    {
        [$user, $managedServer] = $this->requireManagedServer();

        $limits = $this->channelLimits($user['platform'], $managedServer['id']);

        $this->renderSection('limits', $user, $managedServer, [
            'limits' => $limits,
            'hasExemptions' => $this->platform($user['platform'])['exemptions'],
            // Discord is asked for the roles only when the server has a channel to leave someone alone on
            'exemptions' => empty($limits['channels']) || !$this->platform($user['platform'])['exemptions']
                ? null
                : $this->exemptions($user['platform'], $managedServer['id']),
            'messageCheck' => $this->messageCheck($user['platform'], $managedServer['id']),
        ]);
    }

    public function logs(): void
    {
        [$user, $managedServer] = $this->requireManagedServer();

        $this->renderSection('logs', $user, $managedServer, [
            'log' => $this->moderationLog($user['platform'], $managedServer['id']),
        ]);
    }

    /** One event of the logs on its own page, to link to; only an event of the chosen server is shown. */
    public function logEvent(int $id): void
    {
        [$user, $managedServer] = $this->requireManagedServer();

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

        $event = $found['event'];

        $this->renderSection(
            'logs',
            $user,
            $managedServer,
            [
                'logEvent' => [
                    'event' => $event,
                    'author' => $this->eventAuthor($user['platform'], $managedServer['id'], $event),
                    'historyDays' => self::AUTHOR_HISTORY_DAYS,
                ],
            ],
            view: 'log-event',
            pageTitle: sprintf($this->lang()->text('logs.event.title'), $event->getId()),
        );
    }

    /**
     * Shows a section inside the frame every section shares: the account, the navigation and what the last form did.
     *
     * @param array{platform: string, id: string, name: string, avatarUrl: ?string} $user
     * @param array{id: string, name: string}|null $managedServer null only on the list of servers, while none is chosen
     * @param array<string, mixed> $data what the section's own view shows
     * @param string|null $view the view in views/panel/, when it is not named like the section
     * @param string|null $pageTitle when it is not the name of the section
     */
    private function renderSection(
        string $section,
        array $user,
        ?array $managedServer,
        array $data,
        ?string $view = null,
        ?string $pageTitle = null,
    ): void {
        $navigation = Config::load('panel-navigation');
        // a text with a version for the account's platform, such as "channel" where Discord says "server", shows in it
        $this->lang()->usePlatform($user['platform']);

        // every section but the list of servers is about one server, so its link waits until one is chosen there
        foreach ($navigation as $key => $item) {
            $navigation[$key]['locked'] = $key !== 'servers' && $managedServer === null;
        }

        // what the last form did, shown once
        $notice = $_SESSION['notice'] ?? null;
        $savedChannelId = $_SESSION['savedChannelId'] ?? null;
        unset($_SESSION['notice'], $_SESSION['savedChannelId']);

        $this->render('panel', $data + [
            'pageTitle' => $pageTitle ?? $this->lang()->text($navigation[$section]['name']),
            'navigation' => $navigation,
            'activeSection' => $section,
            'sectionView' => $view ?? $section,
            'currentAccount' => $user,
            'currentPlatformName' => $this->platform($user['platform'])['name'],
            'managedServer' => $managedServer,
            'notice' => is_string($notice['text'] ?? null) ? ['text' => $notice['text'], 'isError' => ($notice['isError'] ?? false) === true] : null,
            'savedChannelId' => is_int($savedChannelId) ? $savedChannelId : null,
        ]);
    }

    /**
     * Each server with whether the bot is already there and whether it is the chosen one, and for a server without
     * the bot the link that invites it; on a platform without invites the link is null and the panel connects the server.
     *
     * @param list<string> $idsWithBot
     * @return list<array{id: string, name: string, initials: string, iconUrl: ?string, inviteUrl: ?string, hasBot: bool, isManaged: bool}>
     */
    private function serverCards(string $platform, array $servers, array $idsWithBot, ?array $managedServer): array
    {
        $hasInvites = $this->platform($platform)['connect'] === 'invite';
        $list = [];

        foreach ($servers as $server) {
            $iconUrl = $server['iconUrl'] ?? null;

            $list[] = [
                'id' => $server['id'],
                'name' => $server['name'],
                // shown instead of a missing icon
                'initials' => $this->initials($server['name']),
                'iconUrl' => is_string($iconUrl) ? $iconUrl : null,
                // only Discord has invites so far
                'inviteUrl' => !$hasInvites ? null : DiscordClient::AUTHORIZE_URL . '?' . http_build_query([
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
        $offers = array_column(Config::load('models'), null, 'slug');
        $models = $this->availableModels();
        $isBotConnected = null;
        $stats = null;

        try {
            $pdo = $this->database();
            $seconds = (new ServiceStatusRepository($pdo))->secondsSinceSeen(ServiceStatusRepository::bot($platform));
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
     * The name of the bot's account when the bot is one that a moderator must be made of, such as on Twitch;
     * null on other platforms, and when it is not known.
     */
    private function botName(string $platform): ?string
    {
        if (!$this->platform($platform)['botIsAccount']) {
            return null;
        }

        try {
            return (new BotAccountRepository($this->database()))->findName($platform);
        } catch (RuntimeException $exception) {
            error_log('Panel could not read the name of the bot account: ' . $exception->getMessage());

            return null;
        }
    }

    /**
     * Whom the bot leaves alone on the chosen server: whether the owner and administrators, the roles that are exempt
     * and the roles that can still be added. Null when the database does not answer; the roles alone are null
     * when Discord does not, since only Discord knows their names.
     *
     * @return array{
     *     admins: bool,
     *     roles: array{
     *         exempt: list<array{id: string, name: ?string, color: ?string}>,
     *         choices: list<array{id: string, name: string, color: ?string}>,
     *     }|null,
     * }|null
     *     name: null for a role deleted on Discord since; color: as in CSS, or null for a role without one
     */
    private function exemptions(string $platform, string $serverId): ?array
    {
        try {
            $saved = (new ServerRepository($this->database()))->findExemptions($platform, $serverId);
        } catch (RuntimeException $exception) {
            error_log('Panel could not read the exemptions: ' . $exception->getMessage());
            $saved = null;
        }

        if ($saved === null) {
            return null;
        }

        try {
            $roles = $this->access()->roles($serverId);
        } catch (RuntimeException $exception) {
            error_log('Panel could not read the roles from Discord: ' . $exception->getMessage());

            return ['admins' => $saved['admins'], 'roles' => null];
        }

        $isExempt = fn(array $role): bool => in_array($role['id'], $saved['roleIds'], true);
        $exempt = array_values(array_filter($roles, $isExempt));

        // a role deleted on Discord has no name any more, but still shows, so it can be taken off the list
        foreach (array_diff($saved['roleIds'], array_column($roles, 'id')) as $deletedId) {
            $exempt[] = ['id' => $deletedId, 'name' => null, 'color' => null];
        }

        return [
            'admins' => $saved['admins'],
            'roles' => [
                'exempt' => $exempt,
                'choices' => array_values(array_filter($roles, fn(array $role): bool => !$isExempt($role))),
            ],
        ];
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

        $slots = array_fill_keys($this->channelSlotsOf($platform), null);

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

        $offers = array_column(Config::load('models'), null, 'slug');

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
        $platforms = Config::load('platforms');
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
