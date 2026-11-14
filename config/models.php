<?php

declare(strict_types=1);

return [
    // slug: the name the database and config/moderation.php know the model by; an offer without one cannot be set on a channel
    [
        "slug" => "basal-pico",
        "label" => "Basal-pico",
        "size" => "0.8B",
        "hint" => "home.models.hint.pico",
        "logo" => "/assets/models/basal.png",
        "github" => "https://github.com/rkinas/basal",
        "recommended" => true,
    ],
    [
        "slug" => "basal-mini",
        "label" => "Basal-mini",
        "size" => "1.5B",
        "hint" => "home.models.hint.mini",
        "logo" => "/assets/models/basal.png",
        "github" => "https://github.com/rkinas/basal",
    ],
    [
        "label" => "Basal-mini GPU",
        "size" => "1.5B",
        "hint" => "home.models.hint.mini_gpu",
        "logo" => "/assets/models/basal.png",
        "github" => "https://github.com/rkinas/basal",
        "pro" => true,
    ],
];
