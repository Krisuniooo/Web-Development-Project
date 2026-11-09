<?php

declare(strict_types=1);

namespace App\Model;

final class Channel
{
    public function __construct(
        private ?int $id,
        private int $slot, // 1 | 2
        private string $platformChannelId,
        private string $name,
        private string $model, // basal-mini | basal-pico
        private string $language, // en | pl
        private string $status, // active | paused
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
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
}
