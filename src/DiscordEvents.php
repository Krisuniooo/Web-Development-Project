<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Turns Gateway events into changes in the database: which servers the bot is on.
 */
final class DiscordEvents
{
    private const PLATFORM = 'discord';

    private ?ServerRepository $servers = null;

    public function __construct(private Database $database)
    {
    }

    public function handle(string $type, array $data): void
    {
        if ($type === 'READY') {
            // a new database connection for every login: after any error the bot logs in again,
            // so a broken connection is never used twice
            $this->servers = new ServerRepository($this->database->connect());

            // READY lists every server the bot is on; one missing here was left while the bot was offline
            $kept = array_filter(
                array_column(is_array($data['guilds'] ?? null) ? $data['guilds'] : [], 'id'),
                self::isServerId(...),
            );
            $this->servers()->markRemovedExcept(self::PLATFORM, array_values($kept));
        } elseif ($type === 'GUILD_CREATE' || $type === 'GUILD_UPDATE') {
            $id = $data['id'] ?? null;
            $name = $data['name'] ?? null;

            if (self::isServerId($id) && is_string($name) && $name !== '' && !self::isOutage($data)) {
                $this->servers()->saveActive(self::PLATFORM, $id, $name);
            }
        } elseif ($type === 'GUILD_DELETE') {
            $id = $data['id'] ?? null;

            if (self::isServerId($id) && !self::isOutage($data)) {
                $this->servers()->markRemoved(self::PLATFORM, $id);
            }
        }
    }

    private function servers(): ServerRepository
    {
        return $this->servers ?? throw new RuntimeException('A server event came before READY');
    }

    // the server is down at Discord for a while, but the bot is still on it
    private static function isOutage(array $data): bool
    {
        return ($data['unavailable'] ?? false) === true;
    }

    private static function isServerId(mixed $id): bool
    {
        return is_string($id) && preg_match('/^\d{1,20}$/', $id) === 1;
    }
}
