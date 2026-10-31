<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;

final class ChannelRule
{
    public function __construct(
        private int $channelId,
        private string $category, // racism | harassment | profanity
        private bool $enabled,
        private string $action, // flag | delete | timeout | ban
        private ?float $threshold = null,
        private ?DateTimeImmutable $createdAt = null,
        private ?DateTimeImmutable $updatedAt = null,
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

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getThreshold(): ?float
    {
        return $this->threshold;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
