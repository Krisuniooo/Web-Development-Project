<?php

declare(strict_types=1);

namespace App\Discord;

/**
 * Who is an administrator on each server the bot is on. A message says which roles its author has, but not what
 * the roles allow, so the bot remembers every server's owner and roles from the Gateway and keeps them up to date.
 * Only in memory: after each login Discord sends all of it again.
 */
final class DiscordAdministrators
{
    /**
     * @var array<string, array{id: string, owner_id: mixed, roles: array<string, array>}> by server id,
     *     in the shape DiscordPermissions reads; the roles by their id
     */
    private array $servers = [];

    /** Before a new login, which brings every server again. */
    public function forgetAll(): void
    {
        $this->servers = [];
    }

    /** Takes what a Gateway event says about a server's owner and roles; any other event changes nothing. */
    public function update(string $type, array $data): void
    {
        if ($type === 'GUILD_CREATE' || $type === 'GUILD_UPDATE') {
            $this->remember($data);

            return;
        }

        // the events about one role name the server in guild_id, the one about a server that is gone in id
        $serverId = $data[$type === 'GUILD_DELETE' ? 'id' : 'guild_id'] ?? null;

        // a role of a server the bot has not heard of yet comes again with the server itself
        if (!is_string($serverId) || !isset($this->servers[$serverId])) {
            return;
        }

        if ($type === 'GUILD_DELETE') {
            unset($this->servers[$serverId]);
        } elseif ($type === 'GUILD_ROLE_CREATE' || $type === 'GUILD_ROLE_UPDATE') {
            $role = $data['role'] ?? null;

            if (is_array($role) && is_string($role['id'] ?? null)) {
                $this->servers[$serverId]['roles'][$role['id']] = $role;
            }
        } elseif ($type === 'GUILD_ROLE_DELETE' && is_string($data['role_id'] ?? null)) {
            unset($this->servers[$serverId]['roles'][$data['role_id']]);
        }
    }

    /**
     * Whether the member is the server's owner or has a role with the Administrator permission.
     * Nobody is on a server the bot knows nothing about yet.
     *
     * @param array $member with the user and the role ids
     */
    public function includes(string $serverId, array $member): bool
    {
        if (!isset($this->servers[$serverId])) {
            return false;
        }

        return (DiscordPermissions::inServer($this->servers[$serverId], $member) & DiscordPermissions::ADMINISTRATOR) !== 0;
    }

    private function remember(array $guild): void
    {
        $id = $guild['id'] ?? null;
        $roles = $guild['roles'] ?? null;

        // without roles it is not the whole server, such as one that is down at Discord for a while
        if (!is_string($id) || !is_array($roles)) {
            return;
        }

        $this->servers[$id] = [
            'id' => $id,
            'owner_id' => $guild['owner_id'] ?? null,
            'roles' => array_column(array_filter($roles, 'is_array'), null, 'id'),
        ];
    }
}
