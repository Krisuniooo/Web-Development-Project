<?php

declare(strict_types=1);

namespace App\Discord;

use App\Database;
use App\Moderation\Moderator;
use App\Moderation\PendingRequests;
use CurlHandle;

/** The moderator of Discord's messages: it deletes them through the bot. What it does with a message is in Moderator. */
final class DiscordModerator extends Moderator
{
    public function __construct(
        PendingRequests $requests,
        private DiscordBotClient $discord,
        Database $database,
    ) {
        parent::__construct($requests, $database);
    }

    protected function deleteRequest(string $channelId, string $messageId): CurlHandle
    {
        return $this->discord->deleteMessageRequest($channelId, $messageId);
    }

    protected function checkDeleted(CurlHandle $request, ?string $response): void
    {
        DiscordBotClient::checkDeleted($request, $response);
    }
}
