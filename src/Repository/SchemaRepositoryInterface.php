<?php

declare(strict_types=1);

namespace Bareapi\Repository;

use Bareapi\Entity\Schema;

interface SchemaRepositoryInterface
{
    /**
     * Find the default schema for an object type.
     */
    public function findDefaultSchema(string $objectType): ?Schema;

    /**
     * Find a schema by object type and version.
     */
    public function findByVersion(string $objectType, string $version): ?Schema;

    /**
     * Find all schemas for an object type.
     *
     * @return Schema[]
     */
    public function findByObjectType(string $objectType): array;

    /**
     * Find all unique object types.
     *
     * @return array<int, string>
     */
    public function findAllObjectTypes(): array;

    /**
     * Save a schema entity.
     */
    public function save(Schema $schema): void;

    /**
     * Remove a schema entity.
     */
    public function remove(Schema $schema): void;

    /**
     * Clear the default flag for all schemas of a given object type.
     */
    public function clearDefaultForObjectType(string $objectType): void;
}
