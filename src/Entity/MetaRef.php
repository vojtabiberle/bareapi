<?php

declare(strict_types=1);

namespace Bareapi\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

/**
 * Entity representing a reference from one MetaObject to another.
 *
 * This table maintains a reverse index of x-metastore.refersTo relationships
 * for fast inbound lookups, delete cascade planning, and reference counting.
 */
#[ORM\Entity]
#[ORM\Table(name: 'meta_refs')]
#[ORM\Index(columns: ['project_id', 'to_type', 'to_uuid'], name: 'idx_meta_refs_inbound')]
#[ORM\Index(columns: ['project_id', 'from_type', 'from_uuid'], name: 'idx_meta_refs_outbound')]
class MetaRef
{
    #[ORM\Column(name: 'project_id', nullable: true)]
    private ?int $projectId;

    #[ORM\Id]
    #[ORM\Column(name: 'from_type', length: 100)]
    private string $fromType;

    #[ORM\Id]
    #[ORM\Column(name: 'from_uuid', type: 'uuid')]
    private UuidInterface $fromUuid;

    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private string $path;

    #[ORM\Id]
    #[ORM\Column(name: 'to_type', length: 100)]
    private string $toType;

    #[ORM\Id]
    #[ORM\Column(name: 'to_uuid', type: 'uuid')]
    private UuidInterface $toUuid;

    #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct(
        ?int $projectId,
        string $fromType,
        UuidInterface $fromUuid,
        string $path,
        string $toType,
        UuidInterface $toUuid,
    ) {
        $this->projectId = $projectId;
        $this->fromType = $fromType;
        $this->fromUuid = $fromUuid;
        $this->path = $path;
        $this->toType = $toType;
        $this->toUuid = $toUuid;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getProjectId(): ?int
    {
        return $this->projectId;
    }

    public function getFromType(): string
    {
        return $this->fromType;
    }

    public function getFromUuid(): UuidInterface
    {
        return $this->fromUuid;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getToType(): string
    {
        return $this->toType;
    }

    public function getToUuid(): UuidInterface
    {
        return $this->toUuid;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
