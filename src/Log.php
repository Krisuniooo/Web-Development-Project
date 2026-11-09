<?php

declare(strict_types=1);

namespace App;

/** The log of the processes in bin/, such as the bot: lines on the standard output, which Docker keeps. */
final class Log
{
    /** One line, with the time. */
    public static function line(string $message): void
    {
        echo date('Y-m-d H:i:s') . " $message\n";
    }
}
