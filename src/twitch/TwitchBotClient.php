<?php

declare(strict_types=1);

namespace App\Twitch;

use CurlHandle;
use RuntimeException;

/**
 * Twitch's API as the bot, with the token of its own account: which channels it moderates, listening to a chat,
 * stopping, and deleting messages in it.
 */
final class TwitchBotClient
{
    private const API_URL = 'https://api.twitch.tv/helix';
    private const TIMEOUT_SECONDS = 5;
    private const CONNECT_TIMEOUT_SECONDS = 3;
    // the most channels Twitch gives in one answer, and the most answers read for one list: far more channels
    // than the bot can listen to, at 300 a connection
    private const PAGE_SIZE = 100;
    private const MAX_PAGES = 20;
    // what Twitch answers a question with, a new subscription, and a deletion; and a subscription it no longer has
    private const OK = 200;
    private const ACCEPTED = 202;
    private const NO_CONTENT = 204;
    private const NOT_FOUND = 404;

    public function __construct(private string $clientId, private string $apiUrl, private BotToken $token)
    {
    }

    public static function fromEnvironment(BotToken $token): self
    {
        return new self((string) getenv('TWITCH_CLIENT_ID'), getenv('TWITCH_API_URL') ?: self::API_URL, $token);
    }

    /**
     * Asks Twitch to send the chat messages of a channel over the connection with this session, as the bot's account.
     * Waits for the answer, which is quick, since a connection must have its first subscription within seconds.
     *
     * @return string the id of the subscription, for unsubscribe()
     * @throws RuntimeException with Twitch's reason, and its status as the code; 429 once the connection has
     *     as many subscriptions as Twitch allows on one
     */
    public function subscribeToChat(string $sessionId, string $broadcasterId): string
    {
        if (!TwitchClient::isId($broadcasterId)) {
            throw new RuntimeException('Not a Twitch channel id');
        }

        $request = $this->prepare('/eventsub/subscriptions');
        curl_setopt_array($request, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [...$this->headers(), 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode([
                'type' => 'channel.chat.message',
                'version' => '1',
                // the chat of this channel, read as the bot
                'condition' => ['broadcaster_user_id' => $broadcasterId, 'user_id' => $this->token->userId()],
                'transport' => ['method' => 'websocket', 'session_id' => $sessionId],
            ], JSON_THROW_ON_ERROR),
        ]);

        $response = curl_exec($request);
        self::check($request, is_string($response) ? $response : null, self::ACCEPTED);
        $id = json_decode((string) $response, true)['data'][0]['id'] ?? null;

        if (!is_string($id) || $id === '') {
            throw new RuntimeException('Twitch accepted the subscription without its id');
        }

        return $id;
    }

    /**
     * Stops the messages of a subscription, such as of a channel paused in the panel. One Twitch no longer has,
     * such as after it took it back, counts as stopped too.
     *
     * @throws RuntimeException with Twitch's reason, and its status as the code
     */
    public function unsubscribe(string $subscriptionId): void
    {
        $request = $this->prepare('/eventsub/subscriptions?' . http_build_query(['id' => $subscriptionId]));
        curl_setopt_array($request, [CURLOPT_CUSTOMREQUEST => 'DELETE', CURLOPT_HTTPHEADER => $this->headers()]);
        $response = curl_exec($request);

        if (curl_errno($request) === 0 && curl_getinfo($request, CURLINFO_RESPONSE_CODE) === self::NOT_FOUND) {
            return;
        }

        self::check($request, is_string($response) ? $response : null, self::NO_CONTENT);
    }

    /**
     * The ids of the channels where the bot's account can delete messages now, the only ones worth judging:
     * its own channel, and those it is a moderator of.
     *
     * @return list<string>
     * @throws RuntimeException when Twitch does not say, with its status as the code: 401 also when the account has not
     *     allowed reading the list (user:read:moderated_channels); and when the list is longer than the bot reads
     */
    public function moderatedChannelIds(): array
    {
        // Twitch leaves the account's own channel out of the list, though its owner can delete messages there
        $ids = [$this->token->userId()];
        $query = ['user_id' => $this->token->userId(), 'first' => self::PAGE_SIZE];

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $request = $this->prepare('/moderation/channels?' . http_build_query($query));
            curl_setopt($request, CURLOPT_HTTPHEADER, $this->headers());
            $response = curl_exec($request);
            self::check($request, is_string($response) ? $response : null, self::OK);
            $answer = json_decode((string) $response, true);

            if (!is_array($answer)) {
                throw new RuntimeException('Twitch answered the list of moderated channels with no list');
            }

            foreach (is_array($answer['data'] ?? null) ? $answer['data'] : [] as $channel) {
                $id = is_array($channel) ? ($channel['broadcaster_id'] ?? null) : null;

                if (TwitchClient::isId($id)) {
                    $ids[] = $id;
                }
            }

            // Twitch says where the next part of the list starts, until there is none
            $cursor = $answer['pagination']['cursor'] ?? null;

            if (!is_string($cursor) || $cursor === '') {
                return $ids;
            }

            $query['after'] = $cursor;
        }

        // a cut list would count the channels after the cut as ones the bot does not moderate
        throw new RuntimeException('The bot moderates more channels than it reads at once: ' . self::PAGE_SIZE * self::MAX_PAGES);
    }

    /** The deletion of a message, ready to send; the bot sends it without waiting and gives the finished request to checkDeleted(). */
    public function deleteMessageRequest(string $broadcasterId, string $messageId): CurlHandle
    {
        if (!TwitchClient::isId($broadcasterId)) {
            throw new RuntimeException('Not a Twitch channel id');
        }

        $request = $this->prepare('/moderation/chat?' . http_build_query([
            'broadcaster_id' => $broadcasterId,
            // the bot acts as itself, and Twitch lets it only where it is a moderator
            'moderator_id' => $this->token->userId(),
            'message_id' => $messageId,
        ]));
        curl_setopt_array($request, [CURLOPT_CUSTOMREQUEST => 'DELETE', CURLOPT_HTTPHEADER => $this->headers()]);

        return $request;
    }

    /** Throws with Twitch's reason, such as that the bot is not a moderator of the channel, when the deletion did not happen. */
    public static function checkDeleted(CurlHandle $request, ?string $response): void
    {
        self::check($request, $response, self::NO_CONTENT);
    }

    private static function check(CurlHandle $request, ?string $response, int $expectedStatus): void
    {
        if (curl_errno($request) !== 0) {
            throw new RuntimeException('Twitch did not answer: ' . curl_error($request));
        }

        $status = curl_getinfo($request, CURLINFO_RESPONSE_CODE);

        if ($status !== $expectedStatus) {
            // its own words, such as "not one of the broadcaster's moderators"; they never quote a message
            $reason = json_decode((string) $response, true)['message'] ?? null;
            throw new RuntimeException("Twitch answered with HTTP $status" . (is_string($reason) ? ": $reason" : ''), $status);
        }
    }

    /** @return list<string> */
    private function headers(): array
    {
        return ['Authorization: Bearer ' . $this->token->get(), 'Client-Id: ' . $this->clientId];
    }

    private function prepare(string $path): CurlHandle
    {
        $request = curl_init($this->apiUrl . $path);
        curl_setopt_array($request, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
        ]);

        return $request;
    }
}
