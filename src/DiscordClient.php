<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

final class DiscordClient
{
    private const TIMEOUT_SECONDS = 10;
    // ADMINISTRATOR or MANAGE_GUILD: the same rule Discord uses for adding a bot to a server
    private const CAN_MANAGE_SERVER = (1 << 3) | (1 << 5);

    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private string $redirectUri,
        private string $apiUrl,
    ) {
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
        if (!is_string($id) || !preg_match('/^\d{1,20}$/', $id) || !is_string($name) || $name === '') {
            throw new RuntimeException('Discord user response has no id or name');
        }

        return [
            'id' => $id,
            'name' => $name,
            // the picture the user chose, or else the default one Discord shows for them
            'avatarUrl' => is_string($avatar) && preg_match('/^(a_)?[0-9a-f]{32}$/', $avatar)
                ? "https://cdn.discordapp.com/avatars/$id/$avatar.png?size=64"
                : 'https://cdn.discordapp.com/embed/avatars/' . self::defaultAvatar($id, $user['discriminator'] ?? '0') . '.png',
        ];
    }

    /** Which of Discord's default pictures the user has: by the id for new names, by the old #1234 tag for the rest. */
    private static function defaultAvatar(string $id, mixed $discriminator): int
    {
        if (is_string($discriminator) && preg_match('/^\d{4}$/', $discriminator) && $discriminator !== '0000') {
            return (int) $discriminator % 5;
        }

        return ((int) $id >> 22) % 6;
    }

    /**
     * The servers the user owns or can manage, sorted by name.
     *
     * @return list<array{id: string, name: string, icon: ?string}>
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

            if (!$canManage || !is_string($id) || !preg_match('/^\d{1,20}$/', $id) || !is_string($name) || $name === '') {
                continue;
            }

            $servers[] = [
                'id' => $id,
                'name' => $name,
                // the icon becomes part of an image address, so only a real icon hash is kept
                'icon' => is_string($icon) && preg_match('/^(a_)?[0-9a-f]{32}$/', $icon) ? $icon : null,
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
