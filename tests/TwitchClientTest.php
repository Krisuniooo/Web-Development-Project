<?php

declare(strict_types=1);

namespace Tests;

use App\Twitch\TwitchClient;
use RuntimeException;

/** What the Twitch client decides without asking Twitch: which ids and pictures are safe, and where the user is sent. */
final class TwitchClientTest extends TestCase
{
    private const PICTURE = 'https://static-cdn.jtvnw.net/jtv_user_pictures/3af5f714-profile_image-300x300.png';

    public function testIdIsOnlyDigits(): void
    {
        $this->assertSame(true, TwitchClient::isId('141981764'));
        $this->assertSame(false, TwitchClient::isId('141981764/../1'));
        $this->assertSame(false, TwitchClient::isId('login_name'));
        $this->assertSame(false, TwitchClient::isId(''));
        $this->assertSame(false, TwitchClient::isId(141981764));
        // more digits than any Twitch id has
        $this->assertSame(false, TwitchClient::isId(str_repeat('1', 21)));
    }

    public function testPictureFromTwitchIsKept(): void
    {
        $this->assertSame(self::PICTURE, TwitchClient::pictureUrl(self::PICTURE));
    }

    public function testPictureFromAnotherHostOrWithMarkupIsDropped(): void
    {
        $this->assertSame(null, TwitchClient::pictureUrl('https://evil.example/x.png'));
        // the host only looks like Twitch's
        $this->assertSame(null, TwitchClient::pictureUrl('https://static-cdn.jtvnw.net.evil.example/x.png'));
        $this->assertSame(null, TwitchClient::pictureUrl('http://static-cdn.jtvnw.net/x.png'));
        // it would end the attribute it is printed in
        $this->assertSame(null, TwitchClient::pictureUrl(self::PICTURE . '" onerror="alert(1)'));
        $this->assertSame(null, TwitchClient::pictureUrl(self::PICTURE . "\n"));
        $this->assertSame(null, TwitchClient::pictureUrl(''));
        $this->assertSame(null, TwitchClient::pictureUrl(null));
    }

    public function testUserIsSentToTwitchWithTheStateAndOnlyTheScopeItNeeds(): void
    {
        $url = $this->client()->authorizeUrl('abc123');
        $parts = parse_url($url);
        parse_str($parts['query'], $query);

        $this->assertSame('id.example', $parts['host']);
        $this->assertSame('/oauth2/authorize', $parts['path']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('the-client', $query['client_id']);
        $this->assertSame('https://app.example/auth/twitch/callback', $query['redirect_uri']);
        $this->assertSame('abc123', $query['state']);
        // reading who moderates a channel, and nothing that acts for the user
        $this->assertSame('user:read:moderated_channels', $query['scope']);
        $this->assertSame(false, str_contains($url, 'the-secret'));
    }

    public function testAnExpiredTokenIsToldFromOtherErrors(): void
    {
        $this->assertSame(true, TwitchClient::isUnauthorized(new RuntimeException('Twitch request failed: HTTP 401', 401)));
        $this->assertSame(false, TwitchClient::isUnauthorized(new RuntimeException('Twitch request failed: HTTP 503', 503)));
        $this->assertSame(false, TwitchClient::isUnauthorized(new RuntimeException('Twitch request failed: no connection')));
    }

    private function client(): TwitchClient
    {
        return new TwitchClient(
            'the-client',
            'the-secret',
            'https://app.example/auth/twitch/callback',
            'https://id.example/oauth2',
            'https://api.example/helix',
        );
    }
}
