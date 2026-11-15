<?php

declare(strict_types=1);

namespace Tests;

use App\Discord\DiscordClient;

/** The address of a user's picture, used at login and for the authors in the overview. */
final class DiscordClientTest extends TestCase
{
    private const USER_ID = '359042200778178590';

    public function testChosenPictureIsUsed(): void
    {
        $this->assertSame(
            'https://cdn.discordapp.com/avatars/' . self::USER_ID . '/3af5f714a6b78164d686f4b0e49af67c.png?size=64',
            DiscordClient::avatarUrl(self::USER_ID, '3af5f714a6b78164d686f4b0e49af67c', '0'),
        );
    }

    public function testAnimatedPictureIsUsed(): void
    {
        $this->assertSame(
            'https://cdn.discordapp.com/avatars/' . self::USER_ID . '/a_3af5f714a6b78164d686f4b0e49af67c.png?size=64',
            DiscordClient::avatarUrl(self::USER_ID, 'a_3af5f714a6b78164d686f4b0e49af67c', '0'),
        );
    }

    public function testWithoutAPictureTheDefaultOneComesFromTheId(): void
    {
        // a new name has no #1234 tag, so the default picture follows the ID: (id >> 22) % 6
        $this->assertSame(
            'https://cdn.discordapp.com/embed/avatars/' . ((int) self::USER_ID >> 22) % 6 . '.png',
            DiscordClient::avatarUrl(self::USER_ID, null, '0'),
        );
    }

    public function testOldTagDecidesTheDefaultPicture(): void
    {
        $this->assertSame('https://cdn.discordapp.com/embed/avatars/2.png', DiscordClient::avatarUrl(self::USER_ID, null, '1237'));
    }

    public function testSomethingThatIsNotAPictureHashNeverGoesIntoTheAddress(): void
    {
        $url = DiscordClient::avatarUrl(self::USER_ID, '../../evil', '0');

        $this->assertSame(true, str_starts_with($url, 'https://cdn.discordapp.com/embed/avatars/'));
    }
}
