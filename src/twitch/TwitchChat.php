<?php

declare(strict_types=1);

namespace App\Twitch;

/** What a chat message from Twitch is to the bot: whether it is the bot's business at all, and in the form the queue takes. */
final class TwitchChat
{
    // the badges of people the bot leaves alone: the owner of the channel and its moderators
    private const EXEMPT_BADGES = ['broadcaster', 'moderator'];

    /**
     * The message in the form of ModerationQueue::add(), or null for one the bot does not judge:
     * its own, one without text, one from the owner or a moderator, and one from the chat of another channel.
     *
     * @param array $event the "event" of a channel.chat.message notification
     * @return array{id: string, channelId: string, authorId: string, authorName: string, text: string}|null
     */
    public static function messageFromEvent(array $event, string $botUserId): ?array
    {
        $messageId = $event['message_id'] ?? null;
        $channelId = $event['broadcaster_user_id'] ?? null;
        $authorId = $event['chatter_user_id'] ?? null;
        $authorName = $event['chatter_user_name'] ?? null;
        $text = $event['message']['text'] ?? null;

        // without a real author, channel and text there is nothing to judge, and nobody the event could be about
        if (!is_string($messageId) || $messageId === '' || !TwitchClient::isId($channelId) || !TwitchClient::isId($authorId)) {
            return null;
        }

        if (!is_string($text) || $text === '') {
            return null;
        }

        // the bot's own words
        if ($authorId === $botUserId) {
            return null;
        }

        // in a chat shared by several channels a message shows in all of them, and belongs to the one it was sent in,
        // whose own moderation judges it
        $source = $event['source_broadcaster_user_id'] ?? null;

        if (is_string($source) && $source !== $channelId) {
            return null;
        }

        foreach (is_array($event['badges'] ?? null) ? $event['badges'] : [] as $badge) {
            if (is_array($badge) && in_array($badge['set_id'] ?? null, self::EXEMPT_BADGES, true)) {
                return null;
            }
        }

        return [
            'id' => $messageId,
            'channelId' => $channelId,
            'authorId' => $authorId,
            'authorName' => is_string($authorName) && $authorName !== '' ? $authorName : $authorId,
            'text' => $text,
        ];
    }
}
