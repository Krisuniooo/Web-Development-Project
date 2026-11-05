<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;

final class Channel
{
    public function __construct(
        private ?int $id,
        private int $serverId,
        private int $slot, // 1 | 2
        private string $platformChannelId,
        private string $name,
        private string $model, // basal-mini | basal-pico
        private string $language, // en | pl
        private string $status, // active | paused
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

    public function getServerId(): int
    {
        return $this->serverId;
    }

    public function getSlot(): int
    {
        return $this->slot;
    }

    public function getPlatformChannelId(): string
    {
        return $this->platformChannelId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getLanguage(): string
    {
        return $this->language;
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
