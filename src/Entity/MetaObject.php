<?php

namespace Bareapi\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @phpstan-type DataArray array<string, mixed>
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

    #[ORM\Column(name: 'schema_version', type: 'string', length: 50)]
    private string $schemaVersion;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json', columnDefinition: 'jsonb')]
    private array $data = [];

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(string $type, string $schemaVersion, array $data)
    {
        $this->id = Uuid::uuid4();
        $this->type = $type;
        $this->schemaVersion = $schemaVersion;
        $this->data = $data;
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): UuidInterface
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getSchemaVersion(): string
    {
        return $this->schemaVersion;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setData(array $data): self
    {
        $this->data = $data;
        $this->updatedAt = new DateTimeImmutable();

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

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id->toString(),
            'type' => $this->type,
            'schema_version' => $this->schemaVersion,
            'data' => $this->data,
            'created_at' => $this->createdAt->format(DateTimeImmutable::ATOM),
            'updated_at' => $this->updatedAt->format(DateTimeImmutable::ATOM),
        ];
    }
}
