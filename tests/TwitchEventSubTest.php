<?php

declare(strict_types=1);

namespace Tests;

use App\Twitch\TwitchEventSub;

/** Where the bot lets Twitch move its connection to. */
final class TwitchEventSubTest extends TestCase
{
    private const FAKE_URL = 'ws://127.0.0.1:8080/ws';

    public function testTwitchsOwnAddressesAreTrusted(): void
    {
        $this->assertSame(true, TwitchEventSub::isTrustedReconnectUrl('wss://eventsub.wss.twitch.tv/ws?challenge=abc', TwitchEventSub::URL));
        $this->assertSame(true, TwitchEventSub::isTrustedReconnectUrl('WSS://EventSub.WSS.Twitch.TV/ws', TwitchEventSub::URL));
    }

    public function testTwitchsAddressWithoutTlsIsRefused(): void
    {
        $this->assertSame(false, TwitchEventSub::isTrustedReconnectUrl('ws://eventsub.wss.twitch.tv/ws', TwitchEventSub::URL));
    }

    public function testAddressesThatOnlyLookLikeTwitchsAreRefused(): void
    {
        $this->assertSame(false, TwitchEventSub::isTrustedReconnectUrl('wss://eventsub.twitch.tv.example.com/ws', TwitchEventSub::URL));
        $this->assertSame(false, TwitchEventSub::isTrustedReconnectUrl('wss://nottwitch.tv/ws', TwitchEventSub::URL));
        $this->assertSame(false, TwitchEventSub::isTrustedReconnectUrl('wss://twitch.tv@example.com/ws', TwitchEventSub::URL));
        $this->assertSame(false, TwitchEventSub::isTrustedReconnectUrl('wss://example.com\\.twitch.tv/ws', TwitchEventSub::URL));
        $this->assertSame(false, TwitchEventSub::isTrustedReconnectUrl('not an address', TwitchEventSub::URL));
    }

    public function testFakeEventSubOfTestsMayMoveTheBotOnItsOwnHost(): void
    {
        $this->assertSame(true, TwitchEventSub::isTrustedReconnectUrl('ws://127.0.0.1:8080/ws?reconnect=1', self::FAKE_URL));
        $this->assertSame(false, TwitchEventSub::isTrustedReconnectUrl('ws://10.0.0.5:8080/ws', self::FAKE_URL));
        // the fake's host is not trusted on the real one
        $this->assertSame(false, TwitchEventSub::isTrustedReconnectUrl('ws://127.0.0.1:8080/ws', TwitchEventSub::URL));
    }
}
