<?php

declare(strict_types=1);

return [
    // a level covers values up to its max, sorted from the lowest; the last one has no upper limit
    "levels" => [
        ["name" => "level.off", "max" => 0],
        ["name" => "level.relaxed", "max" => 50],
        ["name" => "level.balanced", "max" => 80],
        ["name" => "level.strict", "max" => PHP_INT_MAX],
    ],
    "limits" => [
        ["name" => "category.harassment", "value" => 100],
        ["name" => "category.racism", "value" => 100],
        ["name" => "category.profanity", "value" => 0],
    ],
];
