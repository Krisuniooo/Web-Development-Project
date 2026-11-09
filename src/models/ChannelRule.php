<?php

declare(strict_types=1);

namespace App\Model;

final class ChannelRule
{
    public function __construct(
        private int $channelId,
        private string $category, // racism | harassment | profanity
        private string $level, // off | relaxed | balanced | strict
        private string $action, // flag | delete | timeout | ban
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
