<?php

declare(strict_types=1);

namespace Tests;

use App\Discord\DiscordPermissions;

final class DiscordPermissionsTest extends TestCase
{
    private const SERVER_ID = '1';
    private const OWNER_ID = '10';
    private const MEMBER_ID = '20';

    private const ROLE_MANAGER = '2';
    private const ROLE_ADMIN = '3';
    private const ROLE_HELPER = '4';

    public function testOwnerMayManageTheServerWithoutAnyRole(): void
    {
        $permissions = DiscordPermissions::inServer($this->server(), $this->member(self::OWNER_ID));

        $this->assertSame(true, $this->has($permissions, DiscordPermissions::MANAGE_GUILD));
    }

    public function testAdministratorMayDoEverything(): void
    {
        $permissions = DiscordPermissions::inServer($this->server(), $this->member(self::MEMBER_ID, [self::ROLE_ADMIN]));

        $this->assertSame(true, $this->has($permissions, DiscordPermissions::MANAGE_GUILD));
        $this->assertSame(true, $this->has($permissions, DiscordPermissions::VIEW_CHANNEL));
    }

    public function testRoleWithManageServerGivesIt(): void
    {
        $permissions = DiscordPermissions::inServer($this->server(), $this->member(self::MEMBER_ID, [self::ROLE_MANAGER]));

        $this->assertSame(true, $this->has($permissions, DiscordPermissions::MANAGE_GUILD));
    }

    public function testMemberWithoutRolesGetsOnlyWhatEveryoneHas(): void
    {
        $permissions = DiscordPermissions::inServer($this->server(), $this->member(self::MEMBER_ID));

        $this->assertSame(DiscordPermissions::VIEW_CHANNEL, $permissions);
    }

    public function testRoleFromAnotherServerGivesNothing(): void
    {
        $permissions = DiscordPermissions::inServer($this->server(), $this->member(self::MEMBER_ID, ['999']));

        $this->assertSame(false, $this->has($permissions, DiscordPermissions::MANAGE_GUILD));
    }

    public function testBrokenServerAndMemberGiveNoPermissions(): void
    {
        $this->assertSame(0, DiscordPermissions::inServer([], []));
        $this->assertSame(0, DiscordPermissions::inServer(['roles' => 'none'], ['roles' => 'none']));
    }

    public function testChannelCanHideItselfFromEveryone(): void
    {
        $channel = $this->channel([['id' => self::SERVER_ID, 'deny' => (string) DiscordPermissions::VIEW_CHANNEL]]);

        $this->assertSame(false, $this->canView($this->member(self::MEMBER_ID), $channel));
    }

    public function testRoleOverwriteShowsAHiddenChannel(): void
    {
        $channel = $this->channel([
            ['id' => self::SERVER_ID, 'deny' => (string) DiscordPermissions::VIEW_CHANNEL],
            ['id' => self::ROLE_HELPER, 'allow' => (string) DiscordPermissions::VIEW_CHANNEL],
        ]);

        $this->assertSame(true, $this->canView($this->member(self::MEMBER_ID, [self::ROLE_HELPER]), $channel));
    }

    public function testMemberOverwriteWinsOverRoleOverwrite(): void
    {
        $channel = $this->channel([
            ['id' => self::ROLE_HELPER, 'allow' => (string) DiscordPermissions::VIEW_CHANNEL],
            ['id' => self::MEMBER_ID, 'deny' => (string) DiscordPermissions::VIEW_CHANNEL],
        ]);

        $this->assertSame(false, $this->canView($this->member(self::MEMBER_ID, [self::ROLE_HELPER]), $channel));
    }

    public function testNoChannelCanHideItselfFromAnAdministratorOrTheOwner(): void
    {
        $channel = $this->channel([
            ['id' => self::SERVER_ID, 'deny' => (string) DiscordPermissions::VIEW_CHANNEL],
            ['id' => self::MEMBER_ID, 'deny' => (string) DiscordPermissions::VIEW_CHANNEL],
            ['id' => self::OWNER_ID, 'deny' => (string) DiscordPermissions::VIEW_CHANNEL],
        ]);

        $this->assertSame(true, $this->canView($this->member(self::MEMBER_ID, [self::ROLE_ADMIN]), $channel));
        $this->assertSame(true, $this->canView($this->member(self::OWNER_ID), $channel));
    }

    /** A server where everyone may see the channels; Discord sends permissions as text. */
    private function server(): array
    {
        return [
            'id' => self::SERVER_ID,
            'owner_id' => self::OWNER_ID,
            'roles' => [
                // @everyone has the id of the server
                ['id' => self::SERVER_ID, 'permissions' => (string) DiscordPermissions::VIEW_CHANNEL],
                ['id' => self::ROLE_MANAGER, 'permissions' => (string) DiscordPermissions::MANAGE_GUILD],
                ['id' => self::ROLE_ADMIN, 'permissions' => (string) DiscordPermissions::ADMINISTRATOR],
                ['id' => self::ROLE_HELPER, 'permissions' => '0'],
            ],
        ];
    }

    /** @param list<string> $roleIds */
    private function member(string $userId, array $roleIds = []): array
    {
        return ['user' => ['id' => $userId], 'roles' => $roleIds];
    }

    private function channel(array $overwrites): array
    {
        return ['id' => '100', 'permission_overwrites' => $overwrites];
    }

    private function canView(array $member, array $channel): bool
    {
        return $this->has(DiscordPermissions::inChannel($this->server(), $member, $channel), DiscordPermissions::VIEW_CHANNEL);
    }

    private function has(int $permissions, int $permission): bool
    {
        return ($permissions & $permission) !== 0;
    }
}
