<?php

declare(strict_types=1);

// The platforms the home page names. One with "authPath" can be logged in with, and then also says
// how the panel differs for it:
// - "connect": how a server gets the bot: "invite" on the platform itself, after which the server shows up here,
//   or "panel", with a button on the list of servers
// - "channelSlots": how many chats of one server the bot can moderate; with one, the server is its own only chat
// - "exemptions": whether the panel can exempt the server's administrators and roles from moderation
return [
    [
        "name" => "Discord",
        "slug" => "discord",
        "loginPath" => "/login",
        "authPath" => "/auth/discord",
        // a user's profile, with their ID in place of %s
        "profileUrl" => "https://discord.com/users/%s",
        "connect" => "invite",
        "channelSlots" => 2,
        "exemptions" => true,
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
