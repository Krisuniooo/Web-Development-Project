<?php

declare(strict_types=1);

namespace App;

/** The files in config/, so no other class has to know where that folder is. */
final class Config
{
    private const DIRECTORY = __DIR__ . '/../config';

    /** What config/<name>.php returns, such as Config::load('platforms') or Config::load('lang/pl'). */
    public static function load(string $name): array
    {
        return require self::DIRECTORY . "/$name.php";
    }

    /** The full path of a file in config/ that is not PHP, such as a frame of the eye. */
    public static function path(string $file): string
    {
        return self::DIRECTORY . '/' . $file;
    }
}
