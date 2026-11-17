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

    public function testBotsAccountIsAskedForTheScopesOfTheBotAndToChooseTheAccount(): void
    {
        $url = $this->client()->botAuthorizeUrl('abc123');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('abc123', $query['state']);
        // to read the chats and to delete messages in them, and nothing else
        $this->assertSame('user:read:chat moderator:manage:chat_messages', $query['scope']);
        // so Twitch shows the consent again, where another account can be chosen
        $this->assertSame('true', $query['force_verify']);
        $this->assertSame(false, str_contains($url, 'the-secret'));
    }

    public function testOwnerIsAskedOnlyToAllowTheBotAsAModerator(): void
    {
        $url = $this->client()->connectAuthorizeUrl('abc123');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('abc123', $query['state']);
        // one permission, which is used once: no reading of the owner's account, no lasting access
        $this->assertSame('channel:manage:moderators', $query['scope']);
        $this->assertSame(false, isset($query['force_verify']));
        $this->assertSame(false, str_contains($url, 'the-secret'));
    }

    public function testTwitchsAnswersToAddingAModeratorAreToldApart(): void
    {
        $this->assertSame('added', TwitchClient::moderatorResult(204, ''));
        $this->assertSame('already', TwitchClient::moderatorResult(400, '{"status":400,"message":"The user specified in user_id is already a moderator in the broadcaster\'s chat room."}'));
        $this->assertSame('vip', TwitchClient::moderatorResult(422, '{"status":422,"message":"The user in user_id is a VIP. To make them a moderator, you must first remove them as a VIP."}'));
    }

    public function testAnyOtherAnswerToAddingAModeratorIsAFailure(): void
    {
        // the channel has too many moderators: also a 422, but not the VIP one
        $this->assertSame('failed', TwitchClient::moderatorResult(422, '{"message":"The broadcaster\'s channel has reached the maximum number of moderators."}'));
        $this->assertSame('failed', TwitchClient::moderatorResult(400, '{"message":"The user cannot become a moderator because they are banned from the channel."}'));
        $this->assertSame('failed', TwitchClient::moderatorResult(429, '{"message":"Too many requests"}'));
        $this->assertSame('failed', TwitchClient::moderatorResult(500, ''));
        // an answer that is not JSON, and one with a message that is not text
        $this->assertSame('failed', TwitchClient::moderatorResult(400, '<html>'));
        $this->assertSame('failed', TwitchClient::moderatorResult(400, '{"message":["already"]}'));
        // 204 means it worked whatever the body says; a 200 is not what Twitch answers
        $this->assertSame('failed', TwitchClient::moderatorResult(200, '{}'));
    }

    public function testLoginDoesNotForceTheConsentAgain(): void
    {
        parse_str(parse_url($this->client()->authorizeUrl('abc123'), PHP_URL_QUERY), $query);

        $this->assertSame(false, isset($query['force_verify']));
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
