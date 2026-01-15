<?php

declare(strict_types=1);

namespace Bareapi\Repository;

use Bareapi\Entity\MetaRef;
use Ramsey\Uuid\UuidInterface;

interface MetaRefRepositoryInterface
{
    /**
     * Find all inbound references to a target object.
     *
     * @return MetaRef[]
     */
    public function findInboundRefs(
        ?int $projectId,
        string $toType,
        UuidInterface $toUuid,
    ): array;

    /**
     * Find all outbound references from a source object.
     *
     * @return MetaRef[]
     */
    public function findOutboundRefs(
        ?int $projectId,
        string $fromType,
        UuidInterface $fromUuid,
    ): array;

    /**
     * Count inbound references grouped by (from_type, path).
     *
     * @return array<int, array{fromType: string, path: string, count: int}>
     */
    public function countInboundRefsByPath(
        ?int $projectId,
        string $toType,
        UuidInterface $toUuid,
    ): array;

    /**
     * Delete all refs from a specific source object.
     *
     * @return int Number of deleted rows
     */
    public function deleteBySource(
        ?int $projectId,
        string $fromType,
        UuidInterface $fromUuid,
    ): int;

    /**
     * Batch insert refs.
     *
     * @param MetaRef[] $refs
     */
    public function batchInsert(array $refs): void;

    /**
     * Sync refs for a source object (delete removed, insert new).
     *
     * @param MetaRef[] $newRefs Expected refs after operation
     */
    public function syncRefs(
        ?int $projectId,
        string $fromType,
        UuidInterface $fromUuid,
        array $newRefs,
    ): void;

    /**
     * Save a single MetaRef entity.
     */
    public function save(MetaRef $ref): void;

    /**
     * Remove a single MetaRef entity.
     */
    public function remove(MetaRef $ref): void;
}
