<?php

declare(strict_types=1);

namespace App\Twitch;

use App\Database;
use App\Moderation\Moderator;
use App\Moderation\PendingRequests;
use CurlHandle;

/** The moderator of Twitch's messages: it deletes them as the bot's account. What it does with a message is in Moderator. */
final class TwitchModerator extends Moderator
{
    public function __construct(
        PendingRequests $requests,
        private TwitchBotClient $twitch,
        Database $database,
    ) {
        parent::__construct($requests, $database);
    }

    protected function deleteRequest(string $channelId, string $messageId): CurlHandle
    {
        return $this->twitch->deleteMessageRequest($channelId, $messageId);
    }

    protected function checkDeleted(CurlHandle $request, ?string $response): void
    {
        TwitchBotClient::checkDeleted($request, $response);
    }
}
