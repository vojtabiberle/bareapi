<?php

declare(strict_types=1);

namespace Bareapi\Schema;

use Bareapi\Exception\InvalidRefersToException;

/**
 * Parser that extracts x-metastore.refersTo definitions from JSON Schema.
 *
 * Walks schema tree and extracts custom keywords for reference declarations.
 */
final class RefersToParser
{
    private const METASTORE_KEY = 'x-metastore';

    /**
     * Parse a schema and return all RefersToDefinition instances.
     *
     * @param array<string, mixed> $schemaData
     * @return RefersToDefinition[]
     */
    public function parse(array $schemaData): array
    {
        $properties = $schemaData['properties'] ?? null;

        if (! is_array($properties)) {
            return [];
        }

        /** @var array<string, mixed> $properties */
        return $this->extractFromProperties($properties, 'data');
    }

    /**
     * Extract refersTo definitions from properties.
     * Handles nested objects and arrays (depth=1 only).
     *
     * @param array<string, mixed> $properties
     * @param string $pathPrefix Current path prefix (e.g., "data")
     * @return RefersToDefinition[]
     */
    private function extractFromProperties(array $properties, string $pathPrefix): array
    {
        $definitions = [];

        foreach ($properties as $fieldName => $fieldDef) {
            if (! is_array($fieldDef) || ! is_string($fieldName)) {
                continue;
            }

            /** @var array<string, mixed> $fieldDef */
            $currentPath = $pathPrefix . '.' . $fieldName;

            // Check if this field has a refersTo definition
            $definition = $this->parseFieldDefinition($fieldDef, $currentPath);
            if ($definition !== null) {
                $definitions[] = $definition;
            }

            // Handle nested objects (depth=1 only)
            $type = $fieldDef['type'] ?? null;

            if ($type === 'object' && isset($fieldDef['properties']) && is_array($fieldDef['properties'])) {
                // Only go one level deep for nested objects
                foreach ($fieldDef['properties'] as $nestedFieldName => $nestedFieldDef) {
                    if (! is_array($nestedFieldDef) || ! is_string($nestedFieldName)) {
                        continue;
                    }
                    /** @var array<string, mixed> $nestedFieldDef */
                    $nestedPath = $currentPath . '.' . $nestedFieldName;
                    $nestedDefinition = $this->parseFieldDefinition($nestedFieldDef, $nestedPath);
                    if ($nestedDefinition !== null) {
                        $definitions[] = $nestedDefinition;
                    }
                }
            }

            // Handle arrays with items (depth=1 only)
            if ($type === 'array' && isset($fieldDef['items']) && is_array($fieldDef['items'])) {
                /** @var array<string, mixed> $itemsDef */
                $itemsDef = $fieldDef['items'];
                $itemsPath = $currentPath;

                // Check if array items have refersTo
                $itemsDefinition = $this->parseFieldDefinition($itemsDef, $itemsPath);
                if ($itemsDefinition !== null) {
                    $definitions[] = $itemsDefinition;
                }

                // Check nested properties in array items
                if (($itemsDef['type'] ?? null) === 'object'
                    && isset($itemsDef['properties'])
                    && is_array($itemsDef['properties'])) {
                    foreach ($itemsDef['properties'] as $itemFieldName => $itemFieldDef) {
                        if (! is_array($itemFieldDef) || ! is_string($itemFieldName)) {
                            continue;
                        }
                        /** @var array<string, mixed> $itemFieldDef */
                        $itemFieldPath = $currentPath . '.' . $itemFieldName;
                        $itemFieldDefinition = $this->parseFieldDefinition($itemFieldDef, $itemFieldPath);
                        if ($itemFieldDefinition !== null) {
                            $definitions[] = $itemFieldDefinition;
                        }
                    }
                }
            }
        }

        return $definitions;
    }

    /**
     * Parse a single field's x-metastore configuration.
     *
     * @param array<string, mixed> $fieldDef
     * @param string $path Full path to this field
     * @throws InvalidRefersToException
     */
    private function parseFieldDefinition(array $fieldDef, string $path): ?RefersToDefinition
    {
        $xMetastore = $fieldDef[self::METASTORE_KEY] ?? null;

        if (! is_array($xMetastore)) {
            return null;
        }

        $refersTo = $xMetastore['refersTo'] ?? null;

        if ($refersTo === null) {
            return null;
        }

        if (! is_array($refersTo)) {
            throw new InvalidRefersToException($path, 'refersTo must be an object');
        }

        $targetType = $refersTo['type'] ?? null;
        if (! is_string($targetType) || $targetType === '') {
            throw new InvalidRefersToException($path, 'refersTo.type is required and must be a non-empty string');
        }

        $targetField = $refersTo['field'] ?? null;
        if ($targetField !== 'uuid') {
            throw new InvalidRefersToException($path, 'refersTo.field must be "uuid" (only supported key in v1)');
        }

        $onDeleteValue = $xMetastore['onDelete'] ?? 'restrict';
        if (! is_string($onDeleteValue)) {
            throw new InvalidRefersToException($path, 'onDelete must be a string');
        }

        $onDelete = OnDeleteBehavior::tryFrom($onDeleteValue);
        if ($onDelete === null) {
            throw new InvalidRefersToException(
                $path,
                "onDelete must be 'restrict' or 'cascade', got '{$onDeleteValue}'"
            );
        }

        return new RefersToDefinition(
            path: $path,
            targetType: $targetType,
            targetField: $targetField,
            onDelete: $onDelete,
        );
    }
}
