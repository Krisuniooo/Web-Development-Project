<?php

declare(strict_types=1);

namespace App\Model;

final class ChannelRule
{
    // as the moderation_action type in the first migration
    public const ACTION_FLAG = 'flag';
    public const ACTION_DELETE = 'delete';
    public const ACTION_TIMEOUT = 'timeout';
    public const ACTION_BAN = 'ban';

    public function __construct(
        private int $channelId,
        private string $category, // racism | harassment | profanity
        private string $level, // off | relaxed | balanced | strict
        private string $action, // one of the ACTION_ constants
    ) {
    }

    public function getChannelId(): int
    {
        return $this->channelId;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function getAction(): string
    {
        return $this->action;
    }
}
