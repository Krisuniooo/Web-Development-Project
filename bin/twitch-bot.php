<?php

declare(strict_types=1);

use App\Database;
use App\Moderation\ModerationQueue;
use App\Moderation\PendingRequests;
use App\Repository\ServiceStatusRepository;
use App\Secrets;
use App\Twitch\BotToken;
use App\Twitch\TwitchBotClient;
use App\Twitch\TwitchClient;
use App\Twitch\TwitchEventSub;
use App\Twitch\TwitchEvents;
use App\Twitch\TwitchModerator;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Exit code 0 means the bot stopped on purpose, so Docker does not start it again.
// A crash ends with another code, and then Docker does.

if (!TwitchClient::isConfigured()) {
    fwrite(STDERR, "The bot is off: TWITCH_CLIENT_ID and TWITCH_CLIENT_SECRET are empty. Copy them from the Twitch Developer Console into .env\n");
    exit(0);
}

try {
    // the database settings are checked here, the connections open when they are first needed
    $database = new Database();
    // the account the bot acts as is given once at /auth/twitch/bot; its token is kept in the database, sealed with APP_KEY
    $token = new BotToken($database, TwitchClient::fromEnvironment(), Secrets::fromEnvironment());
    $twitch = TwitchBotClient::fromEnvironment($token);
    // the questions to the model and the deletions on Twitch, which the connection to EventSub never waits for
    $requests = new PendingRequests();
    $moderator = new TwitchModerator($requests, $twitch, $database);
    $queue = new ModerationQueue($requests, $moderator->act(...), $moderator->countChecked(...));
    $events = new TwitchEvents($database, $queue, $token, $twitch);
    $eventSub = new TwitchEventSub(
        // Twitch's own address, or a fake one for tests
        getenv('TWITCH_EVENTSUB_URL') ?: TwitchEventSub::URL,
        $events->wantedChannels(...),
        $events->subscribe(...),
        $events->unsubscribe(...),
        $events->handleMessage(...),
        // between messages: the report for the panel and the queue; an idle queue still wakes the bot in time for the next report
        function () use ($events, $queue): float {
            $events->reportAlive();

            return min($queue->tick() ?? ServiceStatusRepository::REPORT_SECONDS, ServiceStatusRepository::REPORT_SECONDS);
        },
    );
} catch (RuntimeException $error) {
    fwrite(STDERR, 'The bot stopped: ' . $error->getMessage() . "\n");
    exit(0);
}

$eventSub->run();
