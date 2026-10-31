<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;

final class ModerationEvent
{
    public function __construct(
        private ?int $id,
        private int $channelId,
        private string $platformMessageId,
        private string $authorPlatformId,
        private string $authorName,
        private string $category, // racism | harassment | profanity
        private string $modelSlug,
        private string $action, // flag | delete | timeout | ban
        private ?string $content = null,
        private ?float $score = null,
        private ?DateTimeImmutable $createdAt = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getChannelId(): int
    {
        return $this->channelId;
    }

    public function getPlatformMessageId(): string
    {
        return $this->platformMessageId;
    }

    public function getAuthorPlatformId(): string
    {
        return $this->authorPlatformId;
    }

    public function getAuthorName(): string
    {
        return $this->authorName;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getModelSlug(): string
    {
        return $this->modelSlug;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function getScore(): ?float
    {
        return $this->score;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }
}
