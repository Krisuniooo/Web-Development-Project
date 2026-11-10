<?php

declare(strict_types=1);

namespace Tests;

use App\Discord\DiscordChannels;
use App\Discord\DiscordPermissions;

final class DiscordChannelsTest extends TestCase
{
    private const SERVER_ID = '1';
    private const TEXT = 0;
    private const VOICE = 2;
    private const CATEGORY = 4;

    public function testChannelsComeInTheOrderOfTheSidebar(): void
    {
        $channels = [
            ['id' => '100', 'type' => self::CATEGORY, 'name' => 'Second category', 'position' => 1],
            ['id' => '200', 'type' => self::CATEGORY, 'name' => 'First category', 'position' => 0],
            ['id' => '11', 'type' => self::TEXT, 'name' => 'in-second-category', 'position' => 0, 'parent_id' => '100'],
            ['id' => '12', 'type' => self::TEXT, 'name' => 'in-first-category-lower', 'position' => 3, 'parent_id' => '200'],
            ['id' => '13', 'type' => self::TEXT, 'name' => 'in-first-category-upper', 'position' => 2, 'parent_id' => '200'],
            ['id' => '14', 'type' => self::TEXT, 'name' => 'outside-any-category', 'position' => 9],
        ];

        $this->assertSame(
            ['outside-any-category', 'in-first-category-upper', 'in-first-category-lower', 'in-second-category'],
            array_column($this->visible($channels), 'name'),
        );
    }

    public function testOnlyTextChannelsAreListedWithTheirIdAndName(): void
    {
        $channels = [
            ['id' => '11', 'type' => self::TEXT, 'name' => 'general', 'position' => 0, 'topic' => 'not needed'],
            ['id' => '12', 'type' => self::VOICE, 'name' => 'Voice', 'position' => 1],
            ['id' => '100', 'type' => self::CATEGORY, 'name' => 'Category', 'position' => 0],
        ];

        $this->assertSame([['id' => '11', 'name' => 'general']], $this->visible($channels));
    }

    public function testChannelTheMemberCannotSeeIsLeftOut(): void
    {
        $channels = [
            ['id' => '11', 'type' => self::TEXT, 'name' => 'general', 'position' => 0],
            ['id' => '12', 'type' => self::TEXT, 'name' => 'staff-only', 'position' => 1, 'permission_overwrites' => [
                ['id' => self::SERVER_ID, 'deny' => (string) DiscordPermissions::VIEW_CHANNEL],
            ]],
        ];

        $this->assertSame(['general'], array_column($this->visible($channels), 'name'));
    }

    public function testBrokenEntriesAreIgnored(): void
    {
        $channels = [
            'not a channel',
            ['type' => self::TEXT, 'name' => 'no-id'],
            ['id' => '12', 'type' => self::TEXT],
            ['id' => '13', 'name' => 'no-type'],
            ['id' => '11', 'type' => self::TEXT, 'name' => 'general'],
        ];

        $this->assertSame([['id' => '11', 'name' => 'general']], $this->visible($channels));
    }

    public function testServerWithoutChannelsGivesAnEmptyList(): void
    {
        $this->assertSame([], $this->visible([]));
    }

    /** What a member without roles sees on a server where everyone may see the channels. */
    private function visible(array $channels): array
    {
        $server = [
            'id' => self::SERVER_ID,
            'owner_id' => '10',
            'roles' => [['id' => self::SERVER_ID, 'permissions' => (string) DiscordPermissions::VIEW_CHANNEL]],
        ];

        return DiscordChannels::visibleText($server, ['user' => ['id' => '20'], 'roles' => []], $channels);
    }
}
