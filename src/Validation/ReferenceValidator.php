<?php

declare(strict_types=1);

namespace Bareapi\Validation;

use Bareapi\Exception\ReferenceValidationException;
use Bareapi\Repository\MetaObjectRepositoryInterface;
use Bareapi\Schema\RefersToDefinition;
use Bareapi\Service\SchemaServiceInterface;

/**
 * Validates that all referenced UUIDs exist and are accessible.
 *
 * Used during create/update operations to enforce referential integrity.
 */
final class ReferenceValidator
{
    public function __construct(
        private MetaObjectRepositoryInterface $metaObjectRepository,
        private SchemaServiceInterface $schemaService,
    ) {
    }

    /**
     * Validate all references in the data payload.
     *
     * @param array<string, mixed> $data The payload data
     * @param string $objectType The type being created/updated
     * @param int|null $projectId Project scope
     * @param string $organizationId Organization scope
     * @throws ReferenceValidationException If any reference is invalid
     */
    public function validate(
        array $data,
        string $objectType,
        ?int $projectId,
        string $organizationId,
    ): void {
        $definitions = $this->schemaService->getRefersToDefinitions($objectType);

        if (empty($definitions)) {
            return;
        }

        // Extract all references from the data
        $references = $this->extractReferences($data, $definitions);

        // Batch validate each target type
        foreach ($references as $targetType => $refInfos) {
            $uuids = [];
            foreach ($refInfos as $refInfo) {
                $uuids = array_merge($uuids, $refInfo['uuids']);
            }

            if (empty($uuids)) {
                continue;
            }

            $uniqueUuids = array_unique($uuids);
            $existence = $this->metaObjectRepository->checkUuidsExist(
                $uniqueUuids,
                $targetType,
                $projectId,
                $organizationId
            );

            // Check for missing references
            foreach ($refInfos as $refInfo) {
                foreach ($refInfo['uuids'] as $uuid) {
                    if (! ($existence[$uuid] ?? false)) {
                        throw new ReferenceValidationException([
                            'path' => $refInfo['path'],
                            'type' => $targetType,
                            'uuid' => $uuid,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Extract UUID values from data based on refersTo definitions.
     *
     * @param array<string, mixed> $data
     * @param RefersToDefinition[] $definitions
     * @return array<string, array<int, array{path: string, uuids: string[]}>>
     */
    private function extractReferences(array $data, array $definitions): array
    {
        $references = [];

        foreach ($definitions as $definition) {
            $path = $definition->path;
            $targetType = $definition->targetType;

            // Extract UUIDs at this path
            $uuids = $this->getValuesAtPath($data, $path);

            if (! empty($uuids)) {
                if (! isset($references[$targetType])) {
                    $references[$targetType] = [];
                }
                $references[$targetType][] = [
                    'path' => $path,
                    'uuids' => $uuids,
                ];
            }
        }

        return $references;
    }

    /**
     * Get value(s) at a dotted path, handling arrays.
     *
     * Path format: "data.field" or "data.items.field" for array items.
     *
     * @param array<string, mixed> $data
     * @return string[]
     */
    private function getValuesAtPath(array $data, string $path): array
    {
        // Remove "data." prefix if present (schema paths include "data." prefix)
        if (str_starts_with($path, 'data.')) {
            $path = substr($path, 5);
        }

        return $this->extractValuesRecursive($data, explode('.', $path));
    }

    /**
     * Recursively extract values at path segments.
     *
     * @param string[] $segments
     * @return string[]
     */
    private function extractValuesRecursive(mixed $current, array $segments): array
    {
        if (empty($segments)) {
            // We've reached the target
            if (is_string($current) && $current !== '') {
                return [$current];
            }

            return [];
        }

        if (! is_array($current)) {
            return [];
        }

        $segment = array_shift($segments);

        // Check if this level is an indexed array (list)
        if (array_is_list($current)) {
            // Iterate over array items
            $values = [];
            foreach ($current as $item) {
                if (is_array($item) && isset($item[$segment])) {
                    $values = array_merge(
                        $values,
                        $this->extractValuesRecursive($item[$segment], $segments)
                    );
                } elseif (empty($segments) && is_string($item) && $item !== '') {
                    // Direct array of strings
                    $values[] = $item;
                }
            }

            return $values;
        }

        // Regular object access
        if (! isset($current[$segment])) {
            return [];
        }

        return $this->extractValuesRecursive($current[$segment], $segments);
    }
}
