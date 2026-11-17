<?php

declare(strict_types=1);

namespace App\Discord;

use App\Config;
use CurlHandle;
use RuntimeException;

/** Discord's API as the bot, so the panel sees what the bot sees: channels, roles, who may manage a server, deletions. */
final class DiscordBotClient
{
    private const TIMEOUT_SECONDS = 5;
    private const CONNECT_TIMEOUT_SECONDS = 3;
    // the form Discord asks bots to use: the address of their code and their version
    private const USER_AGENT = 'DiscordBot (%s, 1.0)';

    private string $userAgent;

    public function __construct(private string $token, private string $apiUrl)
    {
        if ($token === '') {
            throw new RuntimeException('DISCORD_BOT_TOKEN is empty');
        }

        $this->userAgent = sprintf(self::USER_AGENT, Config::load('app')['repository']);
    }

    /** The bot with the token from .env, for the panel; throws when the token is empty. */
    public static function fromEnvironment(): self
    {
        return new self(trim((string) getenv('DISCORD_BOT_TOKEN')), DiscordClient::apiUrl());
    }

    /**
     * The server's text channels the bot can see, in the order of Discord's sidebar.
     *
     * @return list<array{id: string, name: string}>
     */
    public function fetchVisibleTextChannels(string $serverId): array
    {
        $botUserId = $this->request('/users/@me')['id'] ?? null;

        if (!is_string($botUserId)) {
            throw new RuntimeException('Discord gave no id of the bot');
        }

        return DiscordChannels::visibleText(
            $this->request("/guilds/$serverId"),
            // the bot's roles on this server decide what it can see
            $this->request("/guilds/$serverId/members/$botUserId"),
            $this->request("/guilds/$serverId/channels"),
        );
    }

    /**
     * The server's roles a person can have, highest first as in Discord's settings: without @everyone,
     * which all members have, and without the roles Discord makes for bots, since bots are never moderated.
     *
     * @return list<array{id: string, name: string, color: ?string}> color: as in CSS, or null for a role without one
     */
    public function fetchRoles(string $serverId): array
    {
        $roles = array_filter(
            $this->request("/guilds/$serverId/roles"),
            fn(mixed $role): bool => is_array($role)
                && DiscordClient::isId($role['id'] ?? null)
                && is_string($role['name'] ?? null)
                // @everyone is the role with the id of the server
                && $role['id'] !== $serverId
                && !isset($role['tags']['bot_id']),
        );
        usort($roles, fn(array $a, array $b): int => (int) ($b['position'] ?? 0) <=> (int) ($a['position'] ?? 0));

        return array_map(fn(array $role): array => [
            'id' => $role['id'],
            'name' => $role['name'],
            'color' => DiscordClient::cssColor($role['color'] ?? null),
        ], $roles);
    }

    /**
     * Whether the user may manage the server now: its owner, an administrator or someone with Manage Server,
     * the same rule as at login.
     */
    public function canManageServer(string $serverId, string $userId): bool
    {
        try {
            $member = $this->request("/guilds/$serverId/members/$userId");
        } catch (RuntimeException $error) {
            // Discord has no such member: the user left, was banned or deleted the account
            if ($error->getCode() === 404) {
                return false;
            }

            throw $error;
        }

        $permissions = DiscordPermissions::inServer($this->request("/guilds/$serverId"), $member);

        return ($permissions & DiscordPermissions::MANAGE_GUILD) !== 0;
    }

    /** The picture of any Discord user, by their ID; Discord answers even after they left every server the bot is on. */
    public function fetchAvatarUrl(string $userId): string
    {
        // the ID goes into the address of the request and of the picture
        if (!DiscordClient::isId($userId)) {
            throw new RuntimeException('Not a Discord user ID');
        }

        $user = $this->request("/users/$userId");

        return DiscordClient::avatarUrl($userId, $user['avatar'] ?? null, $user['discriminator'] ?? '0');
    }

    /** The deletion of a message, ready to send; the bot sends it without waiting and gives the finished request to checkDeleted(). */
    public function deleteMessageRequest(string $channelId, string $messageId): CurlHandle
    {
        $request = $this->prepare("/channels/$channelId/messages/$messageId");
        curl_setopt($request, CURLOPT_CUSTOMREQUEST, 'DELETE');

        return $request;
    }

    /** Throws with Discord's reason, such as a missing permission, when the deletion did not happen. */
    public static function checkDeleted(CurlHandle $request, ?string $response): void
    {
        if (curl_errno($request) !== 0) {
            throw new RuntimeException('Discord did not answer: ' . curl_error($request));
        }

        $status = curl_getinfo($request, CURLINFO_RESPONSE_CODE);

        // Discord answers a deletion with no content
        if ($status !== 204) {
            // its own words, such as "Missing Permissions" or "Unknown Message"; they never quote the message
            $reason = json_decode((string) $response, true)['message'] ?? null;
            throw new RuntimeException("Discord answered with HTTP $status" . (is_string($reason) ? ": $reason" : ''));
        }
    }

    private function prepare(string $path): CurlHandle
    {
        $request = curl_init($this->apiUrl . $path);
        curl_setopt_array($request, [
            CURLOPT_HTTPHEADER => ['Authorization: Bot ' . $this->token],
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
        ]);

        return $request;
    }

    private function request(string $path): array
    {
        $curl = $this->prepare($path);
        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        if ($body === false) {
            throw new RuntimeException("Discord request to $path failed: " . curl_error($curl));
        }

        $data = json_decode($body, true);

        if ($status !== 200 || !is_array($data)) {
            // the status is also the code, for a caller that tells a 404 from the rest
            throw new RuntimeException("Discord request to $path failed: HTTP $status", $status);
        }

        return $data;
    }
}
