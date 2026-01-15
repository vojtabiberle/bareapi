<?php

declare(strict_types=1);

namespace Bareapi\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'meta_objects')]
#[ORM\Index(columns: ['project_id'], name: 'idx_meta_objects_project_id')]
#[ORM\Index(columns: ['object_type', 'project_id'], name: 'idx_meta_objects_type_project')]
#[ORM\Index(columns: ['organization_id', 'project_id', 'object_type'], name: 'idx_meta_objects_org_project_type')]
#[ORM\UniqueConstraint(name: 'uniq_meta_object', columns: ['object_type', 'name', 'branch', 'project_id'])]
class MetaObject implements JsonSerializable
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $uuid;

    #[ORM\Column(name: 'object_type', length: 100)]
    private string $objectType;

    #[ORM\Column(name: 'schema_version', length: 100)]
    private string $schemaVersion;

    #[ORM\Column(length: 50)]
    private string $branch = 'main';

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(name: 'project_id', nullable: true)]
    private ?int $projectId = null;

    #[ORM\Column(name: 'organization_id', type: Types::TEXT)]
    private string $organizationId;

    #[ORM\Column(name: 'last_updated', type: Types::DATETIMETZ_IMMUTABLE)]
    private DateTimeImmutable $lastUpdated;

    #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'deleted_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $deletedAt = null;

    /**
     * @var Collection<int, MetaObjectRevision>
     */
    #[ORM\OneToMany(targetEntity: MetaObjectRevision::class, mappedBy: 'metaObject', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy([
        'revision' => 'DESC',
    ])]
    private Collection $revisions;

    public function __construct(
        string $objectType,
        string $schemaVersion,
        string $name,
        string $organizationId,
    ) {
        $this->uuid = Uuid::uuid7();
        $this->objectType = $objectType;
        $this->schemaVersion = $schemaVersion;
        $this->name = $name;
        $this->organizationId = $organizationId;
        $this->createdAt = new DateTimeImmutable();
        $this->lastUpdated = new DateTimeImmutable();
        $this->revisions = new ArrayCollection();
    }

    public function getUuid(): UuidInterface
    {
        return $this->uuid;
    }

    public function setUuid(UuidInterface $uuid): self
    {
        $this->uuid = $uuid;

        return $this;
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

    public function getSchemaVersion(): string
    {
        return $this->schemaVersion;
    }

    public function setSchemaVersion(string $schemaVersion): self
    {
        $this->schemaVersion = $schemaVersion;

        return $this;
    }

    public function getBranch(): string
    {
        return $this->branch;
    }

    public function setBranch(string $branch): self
    {
        $this->branch = $branch;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getProjectId(): ?int
    {
        return $this->projectId;
    }

    public function setProjectId(?int $projectId): self
    {
        $this->projectId = $projectId;

        return $this;
    }

    public function getOrganizationId(): string
    {
        return $this->organizationId;
    }

    public function setOrganizationId(string $organizationId): self
    {
        $this->organizationId = $organizationId;

        return $this;
    }

    public function getLastUpdated(): DateTimeImmutable
    {
        return $this->lastUpdated;
    }

    public function setLastUpdated(DateTimeImmutable $lastUpdated): self
    {
        $this->lastUpdated = $lastUpdated;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?DateTimeImmutable $deletedAt): self
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * @return Collection<int, MetaObjectRevision>
     */
    public function getRevisions(): Collection
    {
        return $this->revisions;
    }

    public function addRevision(MetaObjectRevision $revision): self
    {
        if (! $this->revisions->contains($revision)) {
            $this->revisions->add($revision);
            $revision->setMetaObject($this);
        }

        return $this;
    }

    public function removeRevision(MetaObjectRevision $revision): self
    {
        $this->revisions->removeElement($revision);

        return $this;
    }

    public function getLatestRevision(): ?MetaObjectRevision
    {
        $filtered = $this->revisions->filter(fn (MetaObjectRevision $r) => $r->getDeletedAt() === null);

        return $filtered->first() ?: null;
    }

    public function getNextRevisionNumber(): int
    {
        $latest = $this->revisions->first();

        return $latest instanceof MetaObjectRevision ? $latest->getRevision() + 1 : 1;
    }

    /**
     * @return array{
     *   uuid: string,
     *   object_type: string,
     *   schema_version: string,
     *   branch: string,
     *   name: string,
     *   project_id: int|null,
     *   organization_id: string,
     *   created_at: string,
     *   last_updated: string,
     *   revision: int|null,
     *   data: array<string, mixed>|null
     * }
     */
    public function jsonSerialize(): array
    {
        $latestRevision = $this->getLatestRevision();

        return [
            'uuid' => $this->uuid->toString(),
            'object_type' => $this->objectType,
            'schema_version' => $this->schemaVersion,
            'branch' => $this->branch,
            'name' => $this->name,
            'project_id' => $this->projectId,
            'organization_id' => $this->organizationId,
            'created_at' => $this->createdAt->format(DateTimeImmutable::ATOM),
            'last_updated' => $this->lastUpdated->format(DateTimeImmutable::ATOM),
            'revision' => $latestRevision?->getRevision(),
            'data' => $latestRevision?->getData(),
        ];
    }
}
