<?php

declare(strict_types=1);

namespace App\Discord;

use RuntimeException;

/** Talks to Discord for the user who logs in, and knows the addresses Discord's pictures and pages have. */
final class DiscordClient
{
    // the version of the API, the same for the user's requests and the bot's
    private const API_URL = 'https://discord.com/api/v10';
    // where the user agrees to log in, and a server owner to add the bot
    public const AUTHORIZE_URL = 'https://discord.com/oauth2/authorize';
    private const CDN_URL = 'https://cdn.discordapp.com';
    // the size of avatars and server icons in pixels: the largest the panel shows them
    private const IMAGE_SIZE = 64;
    // Discord's default pictures: 5 chosen by the old #1234 tag, 6 by the creation time kept in the id from this bit up
    private const LEGACY_DEFAULT_AVATARS = 5;
    private const DEFAULT_AVATARS = 6;
    private const ID_TIMESTAMP_SHIFT = 22;
    // the largest colour of a role
    private const WHITE = 0xFFFFFF;
    private const TIMEOUT_SECONDS = 10;
    // the same rule Discord uses for adding a bot to a server
    private const CAN_MANAGE_SERVER = DiscordPermissions::ADMINISTRATOR | DiscordPermissions::MANAGE_GUILD;

    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private string $redirectUri,
        private string $apiUrl,
    ) {
    }

    /** The API address: Discord's own, or a fake one for tests from DISCORD_API_URL. */
    public static function apiUrl(): string
    {
        return getenv('DISCORD_API_URL') ?: self::API_URL;
    }

    /** Up to 20 digits, like every Discord id, so it is safe in an address. */
    public static function isId(mixed $id): bool
    {
        return is_string($id) && preg_match('/^\d{1,20}$/', $id) === 1;
    }

    /** Whether this is the hash of an avatar or icon, so it is safe in an address; "a_" marks an animated one. */
    public static function isImageHash(mixed $hash): bool
    {
        return is_string($hash) && preg_match('/^(a_)?[0-9a-f]{32}$/', $hash) === 1;
    }

    /**
     * The colour of a role as CSS writes it, or null for a role without one.
     * Discord sends it as one number, red, green and blue together, and 0 when the role has none.
     */
    public static function cssColor(mixed $color): ?string
    {
        return is_int($color) && $color > 0 && $color <= self::WHITE ? sprintf('#%06x', $color) : null;
    }

    /** Trades the one-time code from the redirect for an access token. */
    public function exchangeCode(string $code): string
    {
        $response = $this->request('/oauth2/token', [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->redirectUri,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]),
        ]);

        if (!is_string($response['access_token'] ?? null)) {
            throw new RuntimeException('Discord token response has no access token');
        }

        return $response['access_token'];
    }

    /** @return array{id: string, name: string, avatarUrl: string} */
    public function fetchUser(string $accessToken): array
    {
        $user = $this->request('/users/@me', [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
        ]);

        $id = $user['id'] ?? null;
        $name = $user['global_name'] ?? $user['username'] ?? null;
        $avatar = $user['avatar'] ?? null;

        // the id becomes part of an image address, so it must be a real Discord id
        if (!self::isId($id) || !is_string($name) || $name === '') {
            throw new RuntimeException('Discord user response has no id or name');
        }

        return [
            'id' => $id,
            'name' => $name,
            'avatarUrl' => self::avatarUrl($id, $avatar, $user['discriminator'] ?? '0'),
        ];
    }

    /**
     * The address of a user's picture: the one they chose, or else the default one Discord shows for them.
     *
     * @param string $id digits only, as it becomes part of the address
     */
    public static function avatarUrl(string $id, mixed $avatar, mixed $discriminator): string
    {
        return self::isImageHash($avatar)
            ? self::CDN_URL . "/avatars/$id/$avatar.png?size=" . self::IMAGE_SIZE
            : self::CDN_URL . '/embed/avatars/' . self::defaultAvatar($id, $discriminator) . '.png';
    }

    /**
     * The address of a server's icon, or null when it has none.
     *
     * @param string $id digits only, as it becomes part of the address
     */
    public static function serverIconUrl(string $id, mixed $icon): ?string
    {
        return self::isImageHash($icon) ? self::CDN_URL . "/icons/$id/$icon.png?size=" . self::IMAGE_SIZE : null;
    }

    /** Which of Discord's default pictures the user has: by the id for new names, by the old #1234 tag for the rest. */
    private static function defaultAvatar(string $id, mixed $discriminator): int
    {
        if (is_string($discriminator) && preg_match('/^\d{4}$/', $discriminator) && $discriminator !== '0000') {
            return (int) $discriminator % self::LEGACY_DEFAULT_AVATARS;
        }

        return ((int) $id >> self::ID_TIMESTAMP_SHIFT) % self::DEFAULT_AVATARS;
    }

    /**
     * The servers the user owns or can manage, sorted by name.
     *
     * @return list<array{id: string, name: string, iconUrl: ?string}>
     */
    public function fetchManagedServers(string $accessToken): array
    {
        $guilds = $this->request('/users/@me/guilds', [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
        ]);

        $servers = [];

        foreach ($guilds as $guild) {
            $id = $guild['id'] ?? null;
            $name = $guild['name'] ?? null;
            $icon = $guild['icon'] ?? null;
            $canManage = ($guild['owner'] ?? false) === true
                || ((int) ($guild['permissions'] ?? 0) & self::CAN_MANAGE_SERVER) !== 0;

            if (!$canManage || !self::isId($id) || !is_string($name) || $name === '') {
                continue;
            }

            $servers[] = [
                'id' => $id,
                'name' => $name,
                // the icon becomes part of an image address, so only a real icon hash makes one
                'iconUrl' => self::serverIconUrl($id, $icon),
            ];
        }

        usort($servers, fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $servers;
    }

    private function request(string $path, array $options): array
    {
        $curl = curl_init($this->apiUrl . $path);
        curl_setopt_array($curl, $options + [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
        ]);

        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($curl);

        if ($body === false) {
            throw new RuntimeException("Discord request to $path failed: $curlError");
        }

        $data = json_decode($body, true);

        if ($status !== 200 || !is_array($data)) {
            // only the error code goes to the log, never the body of a token response
            $reason = is_string($data['error'] ?? null) ? $data['error'] : 'no error code';
            throw new RuntimeException("Discord request to $path failed: HTTP $status, $reason");
        }

        return $data;
    }
}
