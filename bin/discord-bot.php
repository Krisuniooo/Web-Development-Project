<?php

declare(strict_types=1);

use App\Database;
use App\DiscordEvents;
use App\DiscordGateway;
use App\ModerationQueue;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Exit code 0 means the bot stopped on purpose, so Docker does not start it again.
// A crash ends with another code, and then Docker does.

$token = trim((string) getenv('DISCORD_BOT_TOKEN'));

if ($token === '') {
    fwrite(STDERR, "The bot is off: DISCORD_BOT_TOKEN is empty. Copy the token from the Discord Developer Portal (Bot > Reset Token) into .env\n");
    exit(0);
}

try {
    // messages wait for the model here, so the Gateway connection never waits for it
    $queue = new ModerationQueue();
    // the database settings are checked here, the connection opens after logging in to Discord
    $events = new DiscordEvents(new Database(), $queue);
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
