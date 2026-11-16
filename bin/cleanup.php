<?php

declare(strict_types=1);

use App\Database;
use App\Log;
use App\Repository\ModerationEventRepository;
use App\Repository\ServerRepository;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Removes the data that is kept longer than needed: how long is set next to the data, in the repositories.
// The cron service in docker-compose.yaml runs it once a day.
// Running it again changes nothing, and with --dry-run it only counts what it would remove:
// docker compose exec php php bin/cleanup.php --dry-run

$isDryRun = in_array('--dry-run', $argv, true);

try {
    $pdo = (new Database())->connect();
    // both steps together or not at all, and a dry run takes them back
    $pdo->beginTransaction();
    // the servers first, so their events are not counted twice
    $servers = (new ServerRepository($pdo))->deleteRemovedFor(ServerRepository::KEEP_REMOVED_DAYS);
    $events = (new ModerationEventRepository($pdo))->stripOlderThan(ModerationEventRepository::KEEP_CONTENT_DAYS);
    $isDryRun ? $pdo->rollBack() : $pdo->commit();
} catch (Throwable $error) {
    // only the first line: the database can add the row after it, and that holds the text of a message
    Log::line('Cleanup failed: ' . strtok($error->getMessage(), "\n"));
    exit(1);
}

Log::line(sprintf(
    'Cleanup%s: removed the text and the author of %d events older than %d days, deleted %d servers the bot left more than %d days ago',
    $isDryRun ? ' (dry run, nothing changed)' : '',
    $events,
    ModerationEventRepository::KEEP_CONTENT_DAYS,
    $servers,
    ServerRepository::KEEP_REMOVED_DAYS,
));
