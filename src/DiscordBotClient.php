<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/** Asks Discord about a server as the bot, so the panel sees the same channels the bot does. */
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

    private function request(string $path): array
    {
        $curl = curl_init($this->apiUrl . $path);
        curl_setopt_array($curl, [
            CURLOPT_HTTPHEADER => ['Authorization: Bot ' . $this->token],
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
        ]);

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
