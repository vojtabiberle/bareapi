<?php

declare(strict_types=1);

namespace Bareapi\Service;

use Bareapi\Entity\MetaObject;
use Bareapi\Entity\MetaRef;
use Bareapi\Repository\MetaRefRepositoryInterface;
use Bareapi\Schema\RefersToDefinition;
use Ramsey\Uuid\Uuid;

/**
 * Maintains the meta_refs reverse index for x-metastore.refersTo relationships.
 *
 * After each successful write operation, this service syncs the reference
 * index to ensure fast inbound lookups, delete cascade planning, and counts.
 */
final class ReferenceIndexService
{
    public function __construct(
        private MetaRefRepositoryInterface $metaRefRepository,
        private SchemaServiceInterface $schemaService,
    ) {
    }

    /**
     * Sync refs after a successful write operation.
     * Computes current refs from data and syncs with stored refs.
     *
     * @param array<string, mixed> $data Current payload data
     */
    public function syncRefsForObject(MetaObject $metaObject, array $data): void
    {
        $refDefs = $this->schemaService->getRefersToDefinitions($metaObject->getObjectType());

        if (empty($refDefs)) {
            // No refs defined, ensure any stale refs are removed
            $this->metaRefRepository->deleteBySource(
                $metaObject->getProjectId(),
                $metaObject->getObjectType(),
                $metaObject->getUuid()
            );

            return;
        }

        $newRefs = $this->computeRefs($metaObject, $data, $refDefs);

        $this->metaRefRepository->syncRefs(
            $metaObject->getProjectId(),
            $metaObject->getObjectType(),
            $metaObject->getUuid(),
            $newRefs
        );
    }

    /**
     * Remove all refs when an object is deleted.
     */
    public function removeRefsForObject(MetaObject $metaObject): void
    {
        $this->metaRefRepository->deleteBySource(
            $metaObject->getProjectId(),
            $metaObject->getObjectType(),
            $metaObject->getUuid()
        );
    }

    /**
     * Compute MetaRef entities from current data.
     *
     * @param array<string, mixed> $data
     * @param RefersToDefinition[] $definitions
     * @return MetaRef[]
     */
    private function computeRefs(
        MetaObject $metaObject,
        array $data,
        array $definitions,
    ): array {
        $refs = [];

        foreach ($definitions as $definition) {
            $uuids = $this->getValuesAtPath($data, $definition->path);

            foreach ($uuids as $uuid) {
                if ($uuid === '' || ! $this->isValidUuid($uuid)) {
                    continue;
                }

                $refs[] = new MetaRef(
                    $metaObject->getProjectId(),
                    $metaObject->getObjectType(),
                    $metaObject->getUuid(),
                    $definition->path,
                    $definition->targetType,
                    Uuid::fromString($uuid)
                );
            }
        }

        return $refs;
    }

    /**
     * Get value(s) at a dotted path, handling arrays.
     *
     * @param array<string, mixed> $data
     * @return string[]
     */
    private function getValuesAtPath(array $data, string $path): array
    {
        // Remove "data." prefix if present
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
            $values = [];
            foreach ($current as $item) {
                if (is_array($item) && isset($item[$segment])) {
                    $values = array_merge(
                        $values,
                        $this->extractValuesRecursive($item[$segment], $segments)
                    );
                } elseif (empty($segments) && is_string($item) && $item !== '') {
                    $values[] = $item;
                }
            }

            return $values;
        }

        if (! isset($current[$segment])) {
            return [];
        }

        return $this->extractValuesRecursive($current[$segment], $segments);
    }

    private function isValidUuid(string $value): bool
    {
        return Uuid::isValid($value);
    }
}
