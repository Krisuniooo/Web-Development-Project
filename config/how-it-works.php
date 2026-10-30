<?php

declare(strict_types=1);

return [
    // a level covers values up to its max, sorted from the lowest; the last one has no upper limit
    "levels" => [
        ["name" => "Not watching", "max" => 0],
        ["name" => "Relaxed", "max" => 50],
        ["name" => "Balanced", "max" => 80],
        ["name" => "Strict", "max" => PHP_INT_MAX],
    ],
    "limits" => [
        ["name" => "Harassment", "value" => 100],
        ["name" => "Racism", "value" => 100],
        ["name" => "Profanity", "value" => 0],
    ],
];
