<?php

declare(strict_types=1);

return [
    [
        "name" => "Discord",
        "slug" => "discord",
        "loginPath" => "/login",
        // a user's profile, with their ID in place of %s
        "profileUrl" => "https://discord.com/users/%s",
        "ctaMessage" => "cta.discord",
    ],
    [
        "name" => "Twitch",
        "slug" => "twitch",
        "ctaMessage" => "cta.twitch",
    ],
    [
        "name" => "Kick",
        "slug" => "kick",
        "ctaMessage" => "cta.kick",
    ],
    [
        "name" => "Telegram",
        "slug" => "telegram",
        "ctaMessage" => "cta.telegram",
    ],
];
