<?php

declare(strict_types=1);

use App\Database;
use App\Log;
use App\Migrator;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Brings the database schema up to date with database/migrations. The migrate service in docker-compose.yaml runs it
// before the app, the bot and cron start. Running it again applies only the new files:
// docker compose exec -T php php bin/migrate.php

try {
    $pdo = (new Database())->connect();
    $applied = (new Migrator($pdo, dirname(__DIR__) . '/database/migrations'))->migrate(Log::line(...));
} catch (Throwable $error) {
    // only the first line, as in bin/cleanup.php
    Log::line('Migration failed: ' . strtok($error->getMessage(), "\n"));
    exit(1);
}

Log::line(match (count($applied)) {
    0 => 'The database is up to date',
    1 => 'Applied 1 migration',
    default => sprintf('Applied %d migrations', count($applied)),
});
