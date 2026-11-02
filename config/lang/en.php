<?php

declare(strict_types=1);

$appName = (require dirname(__DIR__) . '/app.php')['name'];

return [
    "app.name" => $appName,
    "language.label" => "Language",

    "nav.overview" => "Overview",
    "nav.channels" => "Channels",
    "nav.models" => "Models",
    "nav.limits" => "Limits",
    "nav.logs" => "Logs",
    "nav.settings" => "Settings",
    "nav.panel" => "Panel",

    "header.manage" => "Manage $appName",
    "header.manage_on" => "Manage $appName on",
    "theme.to_light" => "Switch to light mode",
    "theme.to_dark" => "Switch to dark mode",
    "panel.back_home" => "Back to home",
    "panel.not_ready" => "This section is not ready yet.",
    "footer.note" => "Decisions are made by models and can be wrong.",
    "account.logout" => "Log out",
    "login.title" => "Log in",
    "login.description" => "Use the account you moderate your community with. There is no separate password.",
    "login.discord" => "Continue with Discord",
    "login.error.cancelled" => "Login was cancelled.",
    "login.error.failed" => "Login failed. Please try again.",
    "login.error.not_configured" => "Discord login is not set up on this server yet.",
    "status.title" => "Server status",
    "status.not_ready" => "This page is not ready yet.",

    "notfound.title" => "Page not found",
    "notfound.text" => "This page does not exist.",
    "notfound.back" => "Back to the home page",

    "home.title" => $appName,
    "home.hero.title" => "KEEP YOUR CHAT CLEAN",
    "home.hero.description" => "A bot that blocks racist, harassing and profane messages before they spoil the conversation.",
    "home.hero.primary" => "Open the panel",
    "home.hero.secondary" => "How it works",
    "home.how.title" => "How it works",
    "home.how.platform" => "Choose a platform",
    "home.how.model" => "Pick a model",
    "home.how.limits" => "Set your limits",
    "home.how.watching" => "Watching #general",
    "home.models.title" => "Supported Models",
    "home.models.description" => "Decision models read each message and answer one question: does it break your rules? English and Polish are supported.",
    "home.models.hint.pico" => "Recommended. English only.",
    "home.models.hint.mini" => "English and Polish.",
    "home.models.hint.mini_gpu" => "English and Polish. GPU accelerated, available for PRO users only.",
    "home.models.recommended" => "Recommended",
    "home.models.github" => "$appName on GitHub",
    "home.cta.title" => "Ready for a calmer chat?",
    "home.cta.button" => "Try Now",
    "home.cta.description" => "Log in with your chat platform and connect your first channel. There is no separate password.",

    "cta.discord" => "Keep my Discord server free of toxicity",
    "cta.twitch" => "Keep my Twitch chat clean while I'm live",
    "cta.kick" => "Block hate in my Kick chat in real time",
    "cta.telegram" => "Protect my Telegram groups from harassment",

    "category.harassment" => "Harassment",
    "category.racism" => "Racism",
    "category.profanity" => "Profanity",
    "level.off" => "Not watching",
    "level.relaxed" => "Relaxed",
    "level.balanced" => "Balanced",
    "level.strict" => "Strict",
];
