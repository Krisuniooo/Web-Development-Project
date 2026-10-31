<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;

final class Account
{
    public function __construct(
        private ?int $id,
        private string $platform,
        private string $platformUserId,
        private string $displayName,
        private ?string $accessTokenEnc = null,
        private ?string $refreshTokenEnc = null,
        private ?DateTimeImmutable $tokenExpiresAt = null,
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

    public function getPlatformUserId(): string
    {
        return $this->platformUserId;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function getAccessTokenEnc(): ?string
    {
        return $this->accessTokenEnc;
    }

    public function getRefreshTokenEnc(): ?string
    {
        return $this->refreshTokenEnc;
    }

    public function getTokenExpiresAt(): ?DateTimeImmutable
    {
        return $this->tokenExpiresAt;
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
