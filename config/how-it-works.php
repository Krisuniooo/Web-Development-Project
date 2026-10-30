<?php

declare(strict_types=1);

return [
    "platforms" => [
        ["name" => "Telegram", "slug" => "telegram"],
        ["name" => "Discord", "slug" => "discord"],
        ["name" => "Twitch", "slug" => "twitch"],
        ["name" => "Kick", "slug" => "kick"],
    ],
    "limits" => [
        ["name" => "Harassment", "level" => "Strict", "value" => 100],
        ["name" => "Racism", "level" => "Strict", "value" => 100],
        ["name" => "Profanity", "level" => "Not watching", "value" => 0],
    ],
];
