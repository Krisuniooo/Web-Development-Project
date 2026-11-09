<?php

declare(strict_types=1);

use App\Database;
use App\Log;
use App\Repository\ModerationEventRepository;
use App\Repository\ServerRepository;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Removes the data that is kept longer than needed. The cron service in docker-compose.yaml runs it once a day.
// Running it again changes nothing, and with --dry-run it only counts what it would remove:
// docker compose exec php php bin/cleanup.php --dry-run

// how long the text and the author of a moderated message are kept; the rest of the event stays for statistics
const CONTENT_DAYS = 30;
// how long the channels, rules and events of a server are kept after the bot left it, in case it comes back
const REMOVED_SERVER_DAYS = 30;

$isDryRun = in_array('--dry-run', $argv, true);

try {
    $pdo = (new Database())->connect();
    // both steps together or not at all, and a dry run takes them back
    $pdo->beginTransaction();
    // the servers first, so their events are not counted twice
    $servers = (new ServerRepository($pdo))->deleteRemovedFor(REMOVED_SERVER_DAYS);
    $events = (new ModerationEventRepository($pdo))->stripOlderThan(CONTENT_DAYS);
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
    CONTENT_DAYS,
    $servers,
    REMOVED_SERVER_DAYS,
));
