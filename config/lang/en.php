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
    "footer.note" => "FOOTER TODO",

    "notfound.title" => "Page not found",
    "notfound.text" => "This page does not exist.",
    "notfound.back" => "Back to the home page",

    "home.title" => $appName,
    "home.hero.title" => "LOREM IPSUM",
    "home.hero.description" => "lorem ipsum dolor sit amet consectetur adipiscing elit",
    "home.hero.primary" => "Button 1",
    "home.hero.secondary" => "Button 2",
    "home.how.title" => "How it works",
    "home.how.platform" => "Choose a platform",
    "home.how.model" => "Pick a model",
    "home.how.limits" => "Set your limits",
    "home.how.watching" => "Watching #general",
    "home.models.title" => "Supported Models",
    "home.models.description" => "lorem ipsum dolor sit amet consectetur adipiscing elit",
    "home.models.pro" => "Available for PRO users only",
    "home.models.recommended" => "Recommended",
    "home.models.github" => "$appName on GitHub",
    "home.cta.title" => "Lorem ipsum dolor sit amet consectetur",
    "home.cta.button" => "Try Now",
    "home.cta.description" => "Lorem ipsum dolor sit amet consectetur adipiscing elit",

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
