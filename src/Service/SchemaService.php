<?php

declare(strict_types=1);

namespace Bareapi\Service;

use Bareapi\Entity\Schema;
use Bareapi\Exception\SchemaNotFoundException;
use Bareapi\Repository\SchemaRepositoryInterface;

final class SchemaService implements SchemaServiceInterface
{
    public function __construct(
        private SchemaRepositoryInterface $schemaRepository,
    ) {
    }

    public function getDefaultSchema(string $objectType): Schema
    {
        $schema = $this->schemaRepository->findDefaultSchema($objectType);

        if ($schema === null) {
            throw new SchemaNotFoundException("No default schema found for type: {$objectType}");
        }

        return $schema;
    }

    public function getSchemaByVersion(string $objectType, string $version): Schema
    {
        $schema = $this->schemaRepository->findByVersion($objectType, $version);

        if ($schema === null) {
            throw new SchemaNotFoundException("Schema not found for type: {$objectType}, version: {$version}");
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSchemaData(string $objectType, ?string $version = null): array
    {
        if ($version !== null) {
            return $this->getSchemaByVersion($objectType, $version)->getSchema();
        }

        return $this->getDefaultSchema($objectType)->getSchema();
    }

    /**
     * @return array<int, string>
     */
    public function getFilterableFields(string $objectType): array
    {
        $schema = $this->getSchemaData($objectType);

        if (! isset($schema['properties']) || ! is_array($schema['properties'])) {
            return [];
        }

        /** @var array<string, mixed> $properties */
        $properties = $schema['properties'];

        $filterable = array_filter(
            $properties,
            fn ($definition) => is_array($definition)
                && array_key_exists('x-filterable', $definition)
                && $definition['x-filterable'] === true
        );

        /** @var array<int, string> $keys */
        $keys = array_keys($filterable);

        return $keys;
    }

    /**
     * @return Schema[]
     */
    public function listSchemas(string $objectType): array
    {
        return $this->schemaRepository->findByObjectType($objectType);
    }

    /**
     * @return array<int, string>
     */
    public function listObjectTypes(): array
    {
        return $this->schemaRepository->findAllObjectTypes();
    }

    public function schemaExists(string $objectType): bool
    {
        try {
            $this->getDefaultSchema($objectType);

            return true;
        } catch (SchemaNotFoundException) {
            return false;
        }
    }
}
