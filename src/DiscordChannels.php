<?php

declare(strict_types=1);

namespace App;

/** Which of a server's channels someone can moderate: the bot from GUILD_CREATE, the panel from the REST API. */
final class DiscordChannels
{
    private const TEXT = 0;
    private const CATEGORY = 4;

    /**
     * The text channels the member can see, in the order of Discord's sidebar:
     * channels outside a category on top, then each category in turn.
     *
     * @param array $guild the server, with its roles
     * @param array $member a member, with the user and the role ids
     * @param array $channels the server's channels, with their permission overwrites
     * @return list<array{id: string, name: string}>
     */
    public static function visibleText(array $guild, array $member, array $channels): array
    {
        $categoryPositions = [];
        $textChannels = [];

        foreach (array_filter($channels, 'is_array') as $channel) {
            if (($channel['type'] ?? null) === self::CATEGORY) {
                $categoryPositions[$channel['id'] ?? ''] = (int) ($channel['position'] ?? 0);
            } elseif (($channel['type'] ?? null) === self::TEXT && is_string($channel['id'] ?? null) && is_string($channel['name'] ?? null)) {
                $textChannels[] = $channel;
            }
        }

        $sortKey = fn(array $channel): array => [
            $categoryPositions[$channel['parent_id'] ?? ''] ?? -1,
            (int) ($channel['position'] ?? 0),
            (int) $channel['id'],
        ];
        usort($textChannels, fn(array $a, array $b): int => $sortKey($a) <=> $sortKey($b));

        $visible = [];

        foreach ($textChannels as $channel) {
            if ((DiscordPermissions::inChannel($guild, $member, $channel) & DiscordPermissions::VIEW_CHANNEL) !== 0) {
                $visible[] = ['id' => $channel['id'], 'name' => $channel['name']];
            }
        }

        return $visible;
    }
}
