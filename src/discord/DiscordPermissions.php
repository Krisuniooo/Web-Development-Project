<?php

declare(strict_types=1);

namespace App\Discord;

/**
 * Discord's permission rules, in the order its documentation gives:
 * the server roles first, then the overwrites of the channel.
 */
final class DiscordPermissions
{
    public const ADMINISTRATOR = 1 << 3;
    public const VIEW_CHANNEL = 1 << 10;

    // every permission at once
    private const ALL = PHP_INT_MAX;

    /**
     * What the member may do in the channel.
     *
     * @param array $guild the server from GUILD_CREATE, with its roles
     * @param array $member a member from GUILD_CREATE, with the user and the role ids
     * @param array $channel a channel from GUILD_CREATE, with its permission overwrites
     */
    public static function inChannel(array $guild, array $member, array $channel): int
    {
        $userId = $member['user']['id'] ?? null;
        $roleIds = is_array($member['roles'] ?? null) ? $member['roles'] : [];

        // the owner and administrators may do everything, no channel can take it away
        if ($userId !== null && $userId === ($guild['owner_id'] ?? null)) {
            return self::ALL;
        }

        // @everyone is the role with the id of the server, and everyone has it
        $rolePermissions = array_column(is_array($guild['roles'] ?? null) ? $guild['roles'] : [], 'permissions', 'id');
        $permissions = (int) ($rolePermissions[$guild['id'] ?? ''] ?? 0);

        foreach ($roleIds as $roleId) {
            $permissions |= (int) ($rolePermissions[$roleId] ?? 0);
        }

        if (($permissions & self::ADMINISTRATOR) !== 0) {
            return self::ALL;
        }

        $overwrites = array_column(
            is_array($channel['permission_overwrites'] ?? null) ? $channel['permission_overwrites'] : [],
            null,
            'id',
        );

        // first what the channel says for @everyone
        $permissions = self::apply($permissions, $overwrites[$guild['id'] ?? ''] ?? null);

        // then for the member's roles, all of them together
        $allow = 0;
        $deny = 0;

        foreach ($roleIds as $roleId) {
            $allow |= (int) ($overwrites[$roleId]['allow'] ?? 0);
            $deny |= (int) ($overwrites[$roleId]['deny'] ?? 0);
        }

        $permissions = ($permissions & ~$deny) | $allow;

        // and last for this one member, which wins over the rest
        return self::apply($permissions, $overwrites[$userId ?? ''] ?? null);
    }

    private static function apply(int $permissions, ?array $overwrite): int
    {
        if ($overwrite === null) {
            return $permissions;
        }

        return ($permissions & ~(int) ($overwrite['deny'] ?? 0)) | (int) ($overwrite['allow'] ?? 0);
    }
}
