<?php

declare(strict_types=1);

namespace Tests;

use App\Twitch\TwitchChat;

/** Which chat messages from Twitch the bot judges, and in what form they go to the queue. */
final class TwitchChatTest extends TestCase
{
    private const BOT_ID = '900';
    private const CHANNEL_ID = '100';
    private const AUTHOR_ID = '200';

    public function testPlainMessageGoesToTheQueue(): void
    {
        $this->assertSame(
            [
                'id' => 'msg-1',
                'channelId' => self::CHANNEL_ID,
                'authorId' => self::AUTHOR_ID,
                'authorName' => 'Viewer',
                'text' => 'hello chat',
            ],
            TwitchChat::messageFromEvent($this->event(), self::BOT_ID),
        );
    }

    public function testBadgesThatAreNotModerationOnesChangeNothing(): void
    {
        $event = $this->event(['badges' => [['set_id' => 'subscriber', 'id' => '12', 'info' => '16'], ['set_id' => 'vip', 'id' => '1', 'info' => '']]]);

        $this->assertSame('msg-1', TwitchChat::messageFromEvent($event, self::BOT_ID)['id']);
    }

    public function testOwnerAndModeratorsAreLeftAlone(): void
    {
        $this->assertSame(null, TwitchChat::messageFromEvent($this->event(['badges' => [['set_id' => 'broadcaster', 'id' => '1', 'info' => '']]]), self::BOT_ID));
        $this->assertSame(null, TwitchChat::messageFromEvent($this->event(['badges' => [['set_id' => 'subscriber', 'id' => '1', 'info' => ''], ['set_id' => 'moderator', 'id' => '1', 'info' => '']]]), self::BOT_ID));
    }

    public function testBotsOwnMessageIsLeftAlone(): void
    {
        $this->assertSame(null, TwitchChat::messageFromEvent($this->event(['chatter_user_id' => self::BOT_ID]), self::BOT_ID));
    }

    public function testMessageSentInAnotherChannelOfASharedChatIsLeftToThatChannel(): void
    {
        $this->assertSame(null, TwitchChat::messageFromEvent($this->event(['source_broadcaster_user_id' => '555']), self::BOT_ID));
        // sent in this channel, shared or not
        $this->assertSame('msg-1', TwitchChat::messageFromEvent($this->event(['source_broadcaster_user_id' => self::CHANNEL_ID]), self::BOT_ID)['id']);
        $this->assertSame('msg-1', TwitchChat::messageFromEvent($this->event(['source_broadcaster_user_id' => null]), self::BOT_ID)['id']);
    }

    public function testMessageWithoutTextIsLeftAlone(): void
    {
        $this->assertSame(null, TwitchChat::messageFromEvent($this->event(['message' => ['text' => '']]), self::BOT_ID));
        $this->assertSame(null, TwitchChat::messageFromEvent($this->event(['message' => []]), self::BOT_ID));
    }

    public function testBrokenEventsAreLeftAlone(): void
    {
        $this->assertSame(null, TwitchChat::messageFromEvent([], self::BOT_ID));
        $this->assertSame(null, TwitchChat::messageFromEvent($this->event(['message_id' => '']), self::BOT_ID));
        $this->assertSame(null, TwitchChat::messageFromEvent($this->event(['message_id' => 123]), self::BOT_ID));
        // an id that is not digits would go into an address
        $this->assertSame(null, TwitchChat::messageFromEvent($this->event(['broadcaster_user_id' => '1/../2']), self::BOT_ID));
        $this->assertSame(null, TwitchChat::messageFromEvent($this->event(['chatter_user_id' => 'someone']), self::BOT_ID));
        // badges that are not a list of badges
        $this->assertSame('msg-1', TwitchChat::messageFromEvent($this->event(['badges' => 'moderator']), self::BOT_ID)['id']);
        $this->assertSame('msg-1', TwitchChat::messageFromEvent($this->event(['badges' => ['moderator']]), self::BOT_ID)['id']);
    }

    public function testAuthorWithoutANameIsShownByItsId(): void
    {
        $this->assertSame(self::AUTHOR_ID, TwitchChat::messageFromEvent($this->event(['chatter_user_name' => '']), self::BOT_ID)['authorName']);
        $this->assertSame(self::AUTHOR_ID, TwitchChat::messageFromEvent($this->event(['chatter_user_name' => null]), self::BOT_ID)['authorName']);
    }

    /** What EventSub sends for a message, with the given fields changed. */
    private function event(array $changes = []): array
    {
        return $changes + [
            'message_id' => 'msg-1',
            'broadcaster_user_id' => self::CHANNEL_ID,
            'chatter_user_id' => self::AUTHOR_ID,
            'chatter_user_name' => 'Viewer',
            'message' => ['text' => 'hello chat'],
            'badges' => [],
            'source_broadcaster_user_id' => null,
        ];
    }
}
