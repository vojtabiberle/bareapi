<?php

declare(strict_types=1);

namespace Bareapi\Entity;

final class MetaObjectRevision
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private string $uuid,
        private int $revision,
        private array $data,
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
