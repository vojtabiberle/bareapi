<?php

declare(strict_types=1);

namespace Bareapi\Service;

use Bareapi\Exception\SchemaNotFoundException;

final class SchemaService implements SchemaServiceInterface
{
    private string $schemaDir;

    public function __construct(string $schemaDir = __DIR__ . '/../../config/schemas/')
    {
        $this->schemaDir = rtrim($schemaDir, '/') . '/';
    }

    /**
     * @throws SchemaNotFoundException
     */
    /**
     * @return array<int, string>
     */
    public function getFilterableFields(string $type): array
    {
        $schema = $this->loadSchema($type);

        if (! isset($schema['properties']) || ! is_array($schema['properties'])) {
            return [];
        }

        return (array) array_keys(array_filter(
            $schema['properties'],
            fn ($definition) =>
            is_array($definition)
                && array_key_exists('x-filterable', $definition)
                && $definition['x-filterable'] === true
        ));
    }

    /**
     * @return array<string, mixed>
     * @throws SchemaNotFoundException
     */
    /**
     * @return array<string, mixed>
     */
    private function loadSchema(string $type): array
    {
        $file = $this->schemaDir . $type . '.json';
        if (! is_file($file)) {
            throw new SchemaNotFoundException("Schema file not found for type: {$type}");
        }
        $content = file_get_contents($file);
        if ($content === false) {
            throw new SchemaNotFoundException("Failed to read schema file for type: {$type}");
        }
        $schema = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($schema)) {
            throw new SchemaNotFoundException("Invalid schema format for type: {$type}");
        }
        /** @var array<string, mixed> $schema */
        return $schema;
    }
}
