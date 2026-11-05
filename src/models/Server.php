<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;

final class Server
{
    public function __construct(
        private ?int $id,
        private string $platform,
        private string $platformServerId,
        private string $name,
        private string $status, // active | removed
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

    public function getPlatformServerId(): string
    {
        return $this->platformServerId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStatus(): string
    {
        return $this->status;
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
