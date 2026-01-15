<?php

declare(strict_types=1);

namespace Bareapi\Service;

use Bareapi\Entity\Schema;
use Bareapi\Schema\RefersToDefinition;

interface SchemaServiceInterface
{
    /**
     * Get the default schema for a given object type.
     *
     * @throws \Bareapi\Exception\SchemaNotFoundException
     */
    public function getDefaultSchema(string $objectType): Schema;

    /**
     * Get a specific version of a schema.
     *
     * @throws \Bareapi\Exception\SchemaNotFoundException
     */
    public function getSchemaByVersion(string $objectType, string $version): Schema;

    /**
     * Get schema data as array for validation/serialization.
     *
     * @return array<string, mixed>
     * @throws \Bareapi\Exception\SchemaNotFoundException
     */
    public function getSchemaData(string $objectType, ?string $version = null): array;

    /**
     * Get the list of filterable fields for an object type.
     *
     * @return array<int, string>
     * @throws \Bareapi\Exception\SchemaNotFoundException
     */
    public function getFilterableFields(string $objectType): array;

    /**
     * List all schemas for an object type.
     *
     * @return Schema[]
     */
    public function listSchemas(string $objectType): array;

    /**
     * List all unique object types.
     *
     * @return array<int, string>
     */
    public function listObjectTypes(): array;

    /**
     * Check if a schema exists for the given object type.
     */
    public function schemaExists(string $objectType): bool;

    /**
     * Get all refersTo definitions for an object type.
     *
     * @return RefersToDefinition[]
     * @throws \Bareapi\Exception\SchemaNotFoundException
     */
    public function getRefersToDefinitions(string $objectType): array;
}
