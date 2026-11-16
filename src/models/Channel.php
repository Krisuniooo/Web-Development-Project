<?php

declare(strict_types=1);

namespace App\Model;

final class Channel
{
    // as the channel_status type in the first migration; the bot skips a paused channel
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_PAUSED];

    public function __construct(
        private int $id,
        private int $slot, // 1 | 2
        private string $platformChannelId,
        private string $name,
        private string $model, // basal-mini | basal-pico
        private string $language, // en | pl
        private string $status, // one of STATUSES
    ) {
    }

    public function getId(): int
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

    public function isPaused(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }
}
