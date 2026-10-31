<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;

final class Channel
{
    public function __construct(
        private ?int $id,
        private string $platform,
        private string $platformChannelId,
        private string $name,
        private string $modelSlug,
        private string $status, // active | paused | disconnected
        private ?int $connectedByAccountId = null,
        private ?DateTimeImmutable $createdAt = null,
        private ?DateTimeImmutable $updatedAt = null,
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

    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function getPlatformChannelId(): string
    {
        return $this->platformChannelId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getModelSlug(): string
    {
        return $this->modelSlug;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getConnectedByAccountId(): ?int
    {
        return $this->connectedByAccountId;
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
