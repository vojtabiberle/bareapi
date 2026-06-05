<?php

declare(strict_types=1);

namespace Bareapi\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @phpstan-type DataArray array<string, mixed>
 *
 * @property DataArray $data
 */
#[ORM\Entity]
#[ORM\Table(
    name: 'meta_objects',
    indexes: [
        new ORM\Index(name: 'type_idx', columns: ['type']),
    ]
)]
class MetaObject implements JsonSerializable
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(type: 'string', length: 100)]
    private string $type;

    #[ORM\Column(name: 'object_type', type: 'string', length: 100)]
    private string $objectType;

    #[ORM\Column(name: 'schema_version', type: 'string', length: 50)]
    private string $schemaVersion;

    #[ORM\Column(type: 'string', length: 50)]
    private string $branch;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    /**
     * @var array<string, mixed>
     */
    /**
     * @var DataArray
     */
    #[ORM\Column(type: 'json', columnDefinition: 'jsonb')]
    private array $data = [];

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'last_updated', type: 'datetime_immutable')]
    private DateTimeImmutable $lastUpdated;

    #[ORM\Column(name: 'deleted_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $deletedAt;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        string $type,
        string $schemaVersion,
        array $data,
        string $name = '',
        string $branch = 'main',
    ) {
        $this->id = Uuid::uuid4();
        $this->type = $type;
        $this->objectType = $type;
        $this->schemaVersion = $schemaVersion;
        $this->branch = $branch;
        $this->name = $name;
        $this->data = $data;
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->lastUpdated = $now;
        $this->deletedAt = null;
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getObjectType(): string
    {
        return $this->objectType;
    }

    public function getSchemaVersion(): string
    {
        return $this->schemaVersion;
    }

    public function getBranch(): string
    {
        return $this->branch;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return DataArray
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array<string, mixed> $data
     */
    /**
     * Returns a new MetaObject with updated data and updatedAt timestamp.
     *
     * @param array<string, mixed> $data
     */
    public function withData(array $data): self
    {
        $clone = new self(
            $this->type,
            $this->schemaVersion,
            $data,
            $this->name,
            $this->branch
        );
        // Copy ID and createdAt from original
        $reflection = new \ReflectionObject($clone);
        $idProp = $reflection->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($clone, $this->id);

        $createdAtProp = $reflection->getProperty('createdAt');
        $createdAtProp->setAccessible(true);
        $createdAtProp->setValue($clone, $this->createdAt);

        // updatedAt is set to now in constructor
        return $clone;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
        $this->lastUpdated = $updatedAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getLastUpdated(): DateTimeImmutable
    {
        return $this->lastUpdated;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function markDeleted(DateTimeImmutable $deletedAt): void
    {
        $this->deletedAt = $deletedAt;
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array{
     *   id: string,
     *   type: string,
     *   object_type: string,
     *   schema_version: string,
     *   branch: string,
     *   name: string,
     *   data: DataArray,
     *   created_at: string,
     *   updated_at: string,
     *   last_updated: string,
     *   deleted_at: string|null
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id->toString(),
            'type' => $this->type,
            'object_type' => $this->objectType,
            'schema_version' => $this->schemaVersion,
            'branch' => $this->branch,
            'name' => $this->name,
            'data' => $this->data,
            'created_at' => $this->createdAt->format(DateTimeImmutable::ATOM),
            'updated_at' => $this->updatedAt->format(DateTimeImmutable::ATOM),
            'last_updated' => $this->lastUpdated->format(DateTimeImmutable::ATOM),
            'deleted_at' => $this->deletedAt?->format(DateTimeImmutable::ATOM),
        ];
    }
}
