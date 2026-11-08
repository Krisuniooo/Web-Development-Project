<?php

declare(strict_types=1);

use App\Database;
use App\Discord\DiscordBotClient;
use App\Discord\DiscordClient;
use App\Discord\DiscordEvents;
use App\Discord\DiscordGateway;
use App\Discord\DiscordModerator;
use App\Moderation\ModerationQueue;
use App\Moderation\PendingRequests;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Exit code 0 means the bot stopped on purpose, so Docker does not start it again.
// A crash ends with another code, and then Docker does.

$token = trim((string) getenv('DISCORD_BOT_TOKEN'));

if ($token === '') {
    fwrite(STDERR, "The bot is off: DISCORD_BOT_TOKEN is empty. Copy the token from the Discord Developer Portal (Bot > Reset Token) into .env\n");
    exit(0);
}

try {
    // the database settings are checked here, the connections open after logging in to Discord
    $database = new Database();
    // the questions to the model and the deletions on Discord, which the Gateway connection never waits for
    $requests = new PendingRequests();
    $moderator = new DiscordModerator(
        $requests,
        new DiscordBotClient($token, getenv('DISCORD_API_URL') ?: DiscordClient::API_URL),
        $database,
    );
    $queue = new ModerationQueue($requests, $moderator->act(...));
    $events = new DiscordEvents($database, $queue);
    $gateway = new DiscordGateway(
        $token,
        getenv('DISCORD_GATEWAY_URL') ?: 'wss://gateway.discord.gg/?v=10&encoding=json',
        $events->handle(...),
        $queue->tick(...),
    );
    $gateway->run();
} catch (RuntimeException $error) {
    fwrite(STDERR, 'The bot stopped: ' . $error->getMessage() . "\n");
    exit(0);
}
