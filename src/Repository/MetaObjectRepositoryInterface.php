<?php

declare(strict_types=1);

namespace Bareapi\Repository;

use Bareapi\Entity\MetaObject;
use Bareapi\Entity\MetaObjectRevision;
use Ramsey\Uuid\UuidInterface;

interface MetaObjectRepositoryInterface
{
    /**
     * Find a MetaObject by UUID.
     */
    public function findByUuid(UuidInterface $uuid): ?MetaObject;

    /**
     * Find a MetaObject by UUID string.
     */
    public function findByUuidString(string $uuid): ?MetaObject;

    /**
     * List MetaObjects by type with pagination and filtering.
     *
     * @param array<string, mixed> $filters
     * @return MetaObject[]
     */
    public function findByType(
        string $objectType,
        ?int $projectId,
        string $organizationId,
        string $branch = 'main',
        array $filters = [],
        int $limit = 100,
        int $offset = 0,
        bool $includeDeleted = false,
    ): array;

    /**
     * Find by object type, name, branch, and scope.
     */
    public function findByNameAndScope(
        string $objectType,
        string $name,
        string $branch,
        ?int $projectId,
        string $organizationId,
    ): ?MetaObject;

    /**
     * Get a specific revision of an object.
     */
    public function findRevision(UuidInterface $uuid, int $revisionNumber): ?MetaObjectRevision;

    /**
     * List all revisions for an object.
     *
     * @return MetaObjectRevision[]
     */
    public function findRevisions(
        UuidInterface $uuid,
        bool $includeDeleted = false,
    ): array;

    /**
     * List all revisions for a type (across all objects).
     *
     * @return MetaObjectRevision[]
     */
    public function findRevisionsByType(
        string $objectType,
        ?int $projectId,
        string $organizationId,
        string $branch = 'main',
        int $limit = 100,
        int $offset = 0,
    ): array;

    /**
     * Save a MetaObject entity.
     */
    public function save(MetaObject $metaObject): void;

    /**
     * Save a MetaObjectRevision entity.
     */
    public function saveRevision(MetaObjectRevision $revision): void;

    /**
     * Soft-delete a MetaObject.
     */
    public function softDelete(MetaObject $metaObject): void;

    /**
     * Soft-delete a specific revision.
     */
    public function softDeleteRevision(MetaObjectRevision $revision): void;

    /**
     * Hard-delete a MetaObject (use with caution).
     */
    public function remove(MetaObject $metaObject): void;

    /**
     * Count MetaObjects matching criteria.
     *
     * @param array<string, mixed> $filters
     */
    public function count(
        string $objectType,
        ?int $projectId,
        string $organizationId,
        string $branch = 'main',
        array $filters = [],
        bool $includeDeleted = false,
    ): int;
}
