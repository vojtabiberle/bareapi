<?php

declare(strict_types=1);

namespace Bareapi\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'schemas')]
#[ORM\Index(columns: ['object_type'], name: 'idx_schemas_object_type')]
#[ORM\Index(columns: ['is_default'], name: 'idx_schemas_is_default')]
#[ORM\UniqueConstraint(name: 'uniq_schema_object_type_version', columns: ['object_type', 'version'])]
class Schema
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private UuidInterface $id;

    #[ORM\Column(name: 'object_type', length: 100)]
    private string $objectType;

    #[ORM\Column(length: 20)]
    private string $version;

    #[ORM\Column(name: 'is_default')]
    private bool $isDefault = false;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'jsonb')]
    private array $schema;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIMETZ_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(name: 'created_by', type: 'jsonb', nullable: true)]
    private ?array $createdBy = null;

    /**
     * @param array<string, mixed> $schema
     */
    public function __construct(string $objectType, string $version, array $schema)
    {
        $this->id = Uuid::uuid7();
        $this->objectType = $objectType;
        $this->version = $version;
        $this->schema = $schema;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getObjectType(): string
    {
        return $this->objectType;
    }

    public function setObjectType(string $objectType): self
    {
        $this->objectType = $objectType;

        return $this;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): self
    {
        $this->isDefault = $isDefault;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSchema(): array
    {
        return $this->schema;
    }

    /**
     * @param array<string, mixed> $schema
     */
    public function setSchema(array $schema): self
    {
        $this->schema = $schema;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCreatedBy(): ?array
    {
        return $this->createdBy;
    }

    /**
     * @param array<string, mixed>|null $createdBy
     */
    public function setCreatedBy(?array $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }
}
