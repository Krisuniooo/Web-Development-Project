<?php

declare(strict_types=1);

namespace Tests;

use App\Discord\DiscordAdministrators;
use App\Discord\DiscordPermissions;

final class DiscordAdministratorsTest extends TestCase
{
    private const SERVER_ID = '1';
    private const OWNER_ID = '10';
    private const MEMBER_ID = '20';

    private const ROLE_ADMIN = '3';
    private const ROLE_HELPER = '4';

    public function testOwnerAndMemberWithAnAdministratorRoleAreIncluded(): void
    {
        $administrators = $this->administrators();

        $this->assertSame(true, $administrators->includes(self::SERVER_ID, $this->member(self::OWNER_ID)));
        $this->assertSame(true, $administrators->includes(self::SERVER_ID, $this->member(self::MEMBER_ID, [self::ROLE_ADMIN])));
    }

    public function testMemberWithoutAnAdministratorRoleIsNotIncluded(): void
    {
        $administrators = $this->administrators();

        $this->assertSame(false, $administrators->includes(self::SERVER_ID, $this->member(self::MEMBER_ID)));
        $this->assertSame(false, $administrators->includes(self::SERVER_ID, $this->member(self::MEMBER_ID, [self::ROLE_HELPER])));
    }

    public function testNobodyIsIncludedOnAServerTheBotHasNotHeardOf(): void
    {
        $administrators = new DiscordAdministrators();

        $this->assertSame(false, $administrators->includes(self::SERVER_ID, $this->member(self::OWNER_ID)));
    }

    public function testRoleThatGetsAdministratorLaterCounts(): void
    {
        $administrators = $this->administrators();
        $administrators->update('GUILD_ROLE_UPDATE', [
            'guild_id' => self::SERVER_ID,
            'role' => ['id' => self::ROLE_HELPER, 'permissions' => (string) DiscordPermissions::ADMINISTRATOR],
        ]);

        $this->assertSame(true, $administrators->includes(self::SERVER_ID, $this->member(self::MEMBER_ID, [self::ROLE_HELPER])));
    }

    public function testNewRoleWithAdministratorCounts(): void
    {
        $administrators = $this->administrators();
        $administrators->update('GUILD_ROLE_CREATE', [
            'guild_id' => self::SERVER_ID,
            'role' => ['id' => '5', 'permissions' => (string) DiscordPermissions::ADMINISTRATOR],
        ]);

        $this->assertSame(true, $administrators->includes(self::SERVER_ID, $this->member(self::MEMBER_ID, ['5'])));
    }

    public function testDeletedRoleNoLongerCounts(): void
    {
        $administrators = $this->administrators();
        $administrators->update('GUILD_ROLE_DELETE', ['guild_id' => self::SERVER_ID, 'role_id' => self::ROLE_ADMIN]);

        $this->assertSame(false, $administrators->includes(self::SERVER_ID, $this->member(self::MEMBER_ID, [self::ROLE_ADMIN])));
    }

    public function testServerUpdateBringsTheNewOwner(): void
    {
        $administrators = $this->administrators();
        $administrators->update('GUILD_UPDATE', ['owner_id' => self::MEMBER_ID] + $this->server());

        $this->assertSame(true, $administrators->includes(self::SERVER_ID, $this->member(self::MEMBER_ID)));
        $this->assertSame(false, $administrators->includes(self::SERVER_ID, $this->member(self::OWNER_ID)));
    }

    public function testServerTheBotLeftIsForgotten(): void
    {
        $administrators = $this->administrators();
        $administrators->update('GUILD_DELETE', ['id' => self::SERVER_ID]);

        $this->assertSame(false, $administrators->includes(self::SERVER_ID, $this->member(self::OWNER_ID)));
    }

    public function testNewLoginForgetsEveryServer(): void
    {
        $administrators = $this->administrators();
        $administrators->forgetAll();

        $this->assertSame(false, $administrators->includes(self::SERVER_ID, $this->member(self::OWNER_ID)));
    }

    public function testServerThatIsDownAndBrokenEventsChangeNothing(): void
    {
        $administrators = $this->administrators();
        // a server that is down at Discord comes without its roles
        $administrators->update('GUILD_CREATE', ['id' => self::SERVER_ID, 'unavailable' => true]);
        $administrators->update('GUILD_ROLE_UPDATE', ['guild_id' => ['broken'], 'role' => 'broken']);
        $administrators->update('GUILD_ROLE_UPDATE', ['guild_id' => '999', 'role' => ['id' => self::ROLE_HELPER]]);
        $administrators->update('GUILD_ROLE_DELETE', ['guild_id' => self::SERVER_ID]);
        $administrators->update('MESSAGE_CREATE', ['guild_id' => self::SERVER_ID, 'id' => '500']);

        $this->assertSame(true, $administrators->includes(self::SERVER_ID, $this->member(self::MEMBER_ID, [self::ROLE_ADMIN])));
        $this->assertSame(false, $administrators->includes(self::SERVER_ID, $this->member(self::MEMBER_ID, [self::ROLE_HELPER])));
    }

    /** The bot after GUILD_CREATE for one server. */
    private function administrators(): DiscordAdministrators
    {
        $administrators = new DiscordAdministrators();
        $administrators->update('GUILD_CREATE', $this->server());

        return $administrators;
    }

    /** Discord sends permissions as text. */
    private function server(): array
    {
        return [
            'id' => self::SERVER_ID,
            'owner_id' => self::OWNER_ID,
            'roles' => [
                // @everyone has the id of the server
                ['id' => self::SERVER_ID, 'permissions' => (string) DiscordPermissions::VIEW_CHANNEL],
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
}
