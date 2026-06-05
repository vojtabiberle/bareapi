<?php

declare(strict_types=1);

namespace Bareapi\Entity;

final class Schema
{
    /**
     * @param array<string, mixed> $schema
     * @param array<string, mixed>|null $createdBy
     */
    public function __construct(
        private string $id,
        private string $objectType,
        private string $version,
        private bool $isDefault,
        private array $schema,
        private ?string $description,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
        private ?array $createdBy,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getObjectType(): string
    {
        return $this->objectType;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSchema(): array
    {
        return $this->schema;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCreatedBy(): ?array
    {
        return $this->createdBy;
    }
}
