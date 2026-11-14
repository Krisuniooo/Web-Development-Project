<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/** When each background service last said it was working, so the panel can tell a clean chat from a stopped bot. */
final class ServiceStatusRepository
{
    public const DISCORD_BOT = 'discord-bot';

    public function __construct(private PDO $pdo)
    {
    }

    /** Notes that the service works now. */
    public function report(string $service): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO service_status (service) VALUES (:service)
             ON CONFLICT (service) DO UPDATE SET seen_at = now()',
        );
        $statement->execute(['service' => $service]);
    }

    /** How long ago the service last reported, by the database's clock, or null when it never did. */
    public function secondsSinceSeen(string $service): ?int
    {
        $statement = $this->pdo->prepare(
            'SELECT CAST(EXTRACT(EPOCH FROM now() - seen_at) AS INTEGER) FROM service_status WHERE service = :service',
        );
        $statement->execute(['service' => $service]);
        $seconds = $statement->fetchColumn();

        return $seconds === false ? null : (int) $seconds;
    }
}
