<?php

declare(strict_types=1);

namespace App\Service;

use App\Discord\DiscordBotClient;
use App\Model\Channel;
use App\Repository\ServerRepository;
use Closure;
use PDO;
use RuntimeException;

/**
 * Which servers the logged-in user may manage, and which of their channels and roles the bot can see.
 * The platform decides, not the session: Discord is asked again before the chosen server is used,
 * and its answers are kept in the session only briefly, since Discord limits how often the bot may ask.
 */
final class ServerAccess
{
    // how long Discord's answer that the user may manage the server is trusted before asking again
    private const ACCESS_CHECK_SECONDS = 60;
    // how long the channels and the roles from Discord are used before asking again: 2 times a minute of the 10 Discord allows
    private const DISCORD_LIST_SECONDS = 30;

    /** @param Closure(): PDO $database the request's connection, opened when first needed */
    public function __construct(private Closure $database)
    {
    }

    /** @return list<array{id: string, name: string, icon: ?string}> the servers the user could manage at login */
    public function servers(): array
    {
        $servers = $_SESSION['servers'] ?? [];

        return is_array($servers) ? $servers : [];
    }

    /**
     * The chosen server, checked again on every page: it must still be on the user's list, still have the bot,
     * and the user must still be allowed to manage it.
     *
     * @param array{platform: string, id: string} $user
     * @return array{id: string, name: string}|null
     */
    public function managedServer(array $user): ?array
    {
        $id = $_SESSION['managedServerId'] ?? null;

        foreach ($this->servers() as $server) {
            if ($server['id'] === $id) {
                return $this->idsWithBot($user['platform'], [$id]) === [$id] && $this->canStillManage($user['id'], $id)
                    ? ['id' => $server['id'], 'name' => $server['name']]
                    : null;
            }
        }

        return null;
    }

    /**
     * Chooses the server the panel is about: only one on the user's list, and only with the bot on it,
     * since the settings are for the bot. Anything else leaves the choice as it was.
     *
     * @param array{platform: string} $user
     */
    public function choose(array $user, mixed $serverId): void
    {
        $isOwnServer = is_string($serverId) && in_array($serverId, array_column($this->servers(), 'id'), true);

        if ($isOwnServer && $this->idsWithBot($user['platform'], [$serverId]) === [$serverId]) {
            $_SESSION['managedServerId'] = $serverId;
        }
    }

    /**
     * Which of these servers have the bot, from the database, which the bot keeps up to date.
     * None when the database does not answer, so the list of servers still shows, only without the bot.
     *
     * @param list<string> $serverIds
     * @return list<string>
     */
    public function idsWithBot(string $platform, array $serverIds): array
    {
        if ($serverIds === []) {
            return [];
        }

        try {
            return (new ServerRepository(($this->database)()))->findActiveIds($platform, $serverIds);
        } catch (RuntimeException $exception) {
            error_log('Panel could not check which servers have the bot: ' . $exception->getMessage());

            return [];
        }
    }

    /**
     * The server's text channels the bot can see, without those already connected.
     *
     * @param list<Channel> $connected
     * @return list<array{id: string, name: string}>
     * @throws RuntimeException when Discord does not answer
     */
    public function availableChannels(string $serverId, array $connected): array
    {
        $connectedIds = array_map(fn(Channel $channel): string => $channel->getPlatformChannelId(), $connected);

        return array_values(array_filter(
            $this->visibleChannels($serverId),
            fn(array $channel): bool => !in_array($channel['id'], $connectedIds, true),
        ));
    }

    /**
     * The server's roles that can be chosen as exempt from moderation, highest first.
     *
     * @return list<array{id: string, name: string, color: ?string}>
     * @throws RuntimeException when Discord does not answer
     */
    public function roles(string $serverId): array
    {
        return $this->remembered(
            'serverRoles',
            $serverId,
            fn(): array => DiscordBotClient::fromEnvironment()->fetchRoles($serverId),
        );
    }

    /**
     * Asks Discord whether the user may still manage the server and trusts a yes for a while.
     * A no, after a ban, leaving or a lost permission, also takes the server off the list.
     */
    private function canStillManage(string $userId, string $serverId): bool
    {
        $saved = $_SESSION['accessCheck'] ?? null;

        // the answer holds only for the same user and server
        if (
            is_array($saved)
            && ($saved['userId'] ?? null) === $userId
            && ($saved['serverId'] ?? null) === $serverId
            && is_int($saved['time'] ?? null) && $saved['time'] > time() - self::ACCESS_CHECK_SECONDS
        ) {
            return true;
        }

        try {
            $canManage = DiscordBotClient::fromEnvironment()->canManageServer($serverId, $userId);
        } catch (RuntimeException $exception) {
            // without an answer nobody gets in, but the server stays on the list for when Discord answers again
            error_log('Panel could not check the access to a server: ' . $exception->getMessage());

            return false;
        }

        if ($canManage) {
            $_SESSION['accessCheck'] = ['userId' => $userId, 'serverId' => $serverId, 'time' => time()];

            return true;
        }

        unset($_SESSION['managedServerId'], $_SESSION['accessCheck'], $_SESSION['visibleChannels'], $_SESSION['serverRoles']);
        $_SESSION['servers'] = array_values(array_filter(
            $this->servers(),
            fn(array $server): bool => $server['id'] !== $serverId,
        ));

        return false;
    }

    /**
     * The server's text channels the bot can see.
     *
     * @return list<array{id: string, name: string}>
     */
    private function visibleChannels(string $serverId): array
    {
        return $this->remembered(
            'visibleChannels',
            $serverId,
            fn(): array => DiscordBotClient::fromEnvironment()->fetchVisibleTextChannels($serverId),
        );
    }

    /**
     * A list Discord gave about the server, kept in the session under $key for a while; after that $fetch asks Discord again.
     *
     * @param Closure(): array $fetch
     */
    private function remembered(string $key, string $serverId, Closure $fetch): array
    {
        $saved = $_SESSION[$key] ?? null;

        if (
            is_array($saved)
            && ($saved['serverId'] ?? null) === $serverId
            && is_int($saved['time'] ?? null) && $saved['time'] > time() - self::DISCORD_LIST_SECONDS
            && is_array($saved['list'] ?? null)
        ) {
            return $saved['list'];
        }

        $list = $fetch();
        $_SESSION[$key] = ['serverId' => $serverId, 'time' => time(), 'list' => $list];

        return $list;
    }
}
