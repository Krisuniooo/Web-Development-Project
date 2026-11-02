<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

final class DiscordClient
{
    private const TIMEOUT_SECONDS = 10;

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

    /** @return array{id: string, name: string} */
    public function fetchUser(string $accessToken): array
    {
        $user = $this->request('/users/@me', [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
        ]);

        $id = $user['id'] ?? null;
        $name = $user['global_name'] ?? $user['username'] ?? null;

        if (!is_string($id) || $id === '' || !is_string($name) || $name === '') {
            throw new RuntimeException('Discord user response has no id or name');
        }

        return ['id' => $id, 'name' => $name];
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
