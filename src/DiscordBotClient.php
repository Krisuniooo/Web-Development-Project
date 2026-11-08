<?php

declare(strict_types=1);

namespace App;

use CurlHandle;
use RuntimeException;

/** Talks to Discord as the bot: what it can see on a server, so the panel sees the same channels, and deleting messages. */
final class DiscordBotClient
{
    private const TIMEOUT_SECONDS = 5;
    private const CONNECT_TIMEOUT_SECONDS = 3;
    // Discord asks bots to name themselves this way
    private const USER_AGENT = 'DiscordBot (https://github.com/Krisuniooo/Web-Development-Project, 1.0)';

    public function __construct(private string $token, private string $apiUrl)
    {
        if ($token === '') {
            throw new RuntimeException('DISCORD_BOT_TOKEN is empty');
        }
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
            CURLOPT_USERAGENT => self::USER_AGENT,
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
            throw new RuntimeException("Discord request to $path failed: HTTP $status");
        }

        return $data;
    }
}
