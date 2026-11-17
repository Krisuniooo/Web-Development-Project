<?php

declare(strict_types=1);

namespace App\Twitch;

use RuntimeException;

/**
 * Talks to Twitch for the user who logs in: who they are and which channels they may manage,
 * their own and those they moderate. On Twitch no bot can answer that, only the user's own token,
 * so the token is asked at login and again whenever the panel checks the access.
 */
final class TwitchClient
{
    // where users agree to log in and tokens are made and checked
    private const AUTH_URL = 'https://id.twitch.tv/oauth2';
    private const API_URL = 'https://api.twitch.tv/helix';
    // only the list of channels the user moderates; their own name and picture need no permission
    private const SCOPE = 'user:read:moderated_channels';
    // Twitch keeps profile pictures here, and only a picture from here is shown
    private const PICTURE_URL = 'https://static-cdn.jtvnw.net/';
    // the most channels or users Twitch gives in one answer
    private const PAGE_SIZE = 100;
    // the most answers read for one list, so a login never takes long: 500 moderated channels
    private const MAX_PAGES = 5;
    private const TIMEOUT_SECONDS = 10;
    // how Twitch answers a token that expired or was taken back
    private const UNAUTHORIZED = 401;

    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private string $redirectUri,
        private string $authUrl,
        private string $apiUrl,
    ) {
    }

    /** The client with the settings from .env; Twitch's own addresses, or fake ones for tests. */
    public static function fromEnvironment(): self
    {
        return new self(
            (string) getenv('TWITCH_CLIENT_ID'),
            (string) getenv('TWITCH_CLIENT_SECRET'),
            rtrim((string) getenv('APP_URL'), '/') . '/auth/twitch/callback',
            getenv('TWITCH_AUTH_URL') ?: self::AUTH_URL,
            getenv('TWITCH_API_URL') ?: self::API_URL,
        );
    }

    public static function isConfigured(): bool
    {
        return getenv('TWITCH_CLIENT_ID') && getenv('TWITCH_CLIENT_SECRET') && getenv('APP_URL');
    }

    /** Digits only, like every Twitch id, so it is safe in an address. */
    public static function isId(mixed $id): bool
    {
        return is_string($id) && preg_match('/^\d{1,20}$/', $id) === 1;
    }

    /** The address of a profile picture, or null unless it is one of Twitch's own, which is all the page may show. */
    public static function pictureUrl(mixed $url): ?string
    {
        $isFromTwitch = is_string($url)
            && str_starts_with($url, self::PICTURE_URL)
            && preg_match('~^[A-Za-z0-9:/._-]+\z~', $url) === 1;

        return $isFromTwitch ? $url : null;
    }

    /** Where the browser goes for the user to agree; $state comes back with them. */
    public function authorizeUrl(string $state): string
    {
        return $this->authUrl . '/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'scope' => self::SCOPE,
            'redirect_uri' => $this->redirectUri,
            'state' => $state,
        ]);
    }

    /**
     * Trades the one-time code from the redirect for an access token.
     *
     * @return array{accessToken: string, expiresIn: int} expiresIn: in seconds, about four hours
     */
    public function exchangeCode(string $code): array
    {
        $response = $this->request($this->authUrl . '/token', [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->redirectUri,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]),
        ]);

        $accessToken = $response['access_token'] ?? null;
        $expiresIn = $response['expires_in'] ?? null;

        if (!is_string($accessToken) || $accessToken === '' || !is_int($expiresIn) || $expiresIn <= 0) {
            throw new RuntimeException('Twitch token response has no access token or no lifetime');
        }

        return ['accessToken' => $accessToken, 'expiresIn' => $expiresIn];
    }

    /** @return array{id: string, name: string, avatarUrl: ?string} the user the token belongs to */
    public function fetchUser(string $accessToken): array
    {
        $user = $this->users($accessToken, [])[0] ?? null;

        if ($user === null) {
            throw new RuntimeException('Twitch user response has no id or name');
        }

        return ['id' => $user['id'], 'name' => $user['name'], 'avatarUrl' => $user['pictureUrl']];
    }

    /**
     * The channels the user may manage: their own first, then those they moderate, sorted by name.
     *
     * @param array{id: string, name: string, avatarUrl: ?string} $user from fetchUser()
     * @return list<array{id: string, name: string, iconUrl: ?string}>
     */
    public function fetchManagedChannels(string $accessToken, array $user): array
    {
        $moderated = $this->moderatedChannels($accessToken, $user['id']);
        usort($moderated, fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        // the list of moderated channels has no pictures, so they are asked for by id, as many at once as Twitch allows
        $pictures = [];

        foreach (array_chunk(array_column($moderated, 'id'), self::PAGE_SIZE) as $ids) {
            foreach ($this->users($accessToken, $ids) as $owner) {
                $pictures[$owner['id']] = $owner['pictureUrl'];
            }
        }

        $channels = [['id' => $user['id'], 'name' => $user['name'], 'iconUrl' => $user['avatarUrl']]];

        foreach ($moderated as $channel) {
            $channels[] = $channel + ['iconUrl' => $pictures[$channel['id']] ?? null];
        }

        return $channels;
    }

    /**
     * Whether the user may manage the channel now: it is their own, or they still moderate it.
     * Throws when Twitch does not answer, and with the code 401 when the token no longer works.
     */
    public function canManageChannel(string $accessToken, string $userId, string $channelId): bool
    {
        if ($userId === $channelId) {
            // nobody loses their own channel, but a banned or deleted account loses its token
            $token = $this->request($this->authUrl . '/validate', [
                CURLOPT_HTTPHEADER => ['Authorization: OAuth ' . $accessToken],
            ]);

            return ($token['user_id'] ?? null) === $userId;
        }

        return in_array($channelId, array_column($this->moderatedChannels($accessToken, $userId), 'id'), true);
    }

    /** The language the channel's owner set for the stream, such as "pl", or null when Twitch names none. */
    public function fetchChannelLanguage(string $accessToken, string $channelId): ?string
    {
        $response = $this->api('/channels?' . http_build_query(['broadcaster_id' => $channelId]), $accessToken);
        $language = $response['data'][0]['broadcaster_language'] ?? null;

        return is_string($language) && $language !== '' ? $language : null;
    }

    public static function isUnauthorized(RuntimeException $error): bool
    {
        return $error->getCode() === self::UNAUTHORIZED;
    }

    /**
     * The channels the user moderates, without their own, which Twitch leaves out of this list.
     *
     * @return list<array{id: string, name: string}>
     */
    private function moderatedChannels(string $accessToken, string $userId): array
    {
        $channels = [];
        $query = ['user_id' => $userId, 'first' => self::PAGE_SIZE];

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $response = $this->api('/moderation/channels?' . http_build_query($query), $accessToken);

            foreach (is_array($response['data'] ?? null) ? $response['data'] : [] as $channel) {
                $id = $channel['broadcaster_id'] ?? null;
                $name = $channel['broadcaster_name'] ?? null;

                if (self::isId($id) && is_string($name) && $name !== '') {
                    $channels[] = ['id' => $id, 'name' => $name];
                }
            }

            // Twitch says where the next part of the list starts, until there is none
            $cursor = $response['pagination']['cursor'] ?? null;

            if (!is_string($cursor) || $cursor === '') {
                break;
            }

            $query['after'] = $cursor;
        }

        return $channels;
    }

    /**
     * The users with these ids, or the token's own user when there are none.
     *
     * @param list<string> $ids at most PAGE_SIZE
     * @return list<array{id: string, name: string, pictureUrl: ?string}>
     */
    private function users(string $accessToken, array $ids): array
    {
        // Twitch wants the same parameter once for each id: id=1&id=2
        $query = implode('&', array_map(fn(string $id): string => 'id=' . rawurlencode($id), $ids));
        $response = $this->api('/users' . ($query === '' ? '' : '?' . $query), $accessToken);
        $users = [];

        foreach (is_array($response['data'] ?? null) ? $response['data'] : [] as $user) {
            $id = $user['id'] ?? null;
            // the name as its owner writes it, with capitals
            $name = $user['display_name'] ?? $user['login'] ?? null;

            if (self::isId($id) && is_string($name) && $name !== '') {
                $users[] = ['id' => $id, 'name' => $name, 'pictureUrl' => self::pictureUrl($user['profile_image_url'] ?? null)];
            }
        }

        return $users;
    }

    private function api(string $path, string $accessToken): array
    {
        return $this->request($this->apiUrl . $path, [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken, 'Client-Id: ' . $this->clientId],
        ]);
    }

    private function request(string $url, array $options): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, $options + [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
        ]);

        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        // without the query, which can hold ids; tokens travel in headers and in the body, never in the address
        $where = strtok($url, '?');

        if ($body === false) {
            throw new RuntimeException("Twitch request to $where failed: " . curl_error($curl));
        }

        $data = json_decode($body, true);

        if ($status !== 200 || !is_array($data)) {
            // the status is also the code, for a caller that tells an expired token from the rest
            throw new RuntimeException("Twitch request to $where failed: HTTP $status", $status);
        }

        return $data;
    }
}
