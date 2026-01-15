<?php

declare(strict_types=1);

namespace Bareapi\Service;

use Bareapi\Entity\MetaObject;
use Bareapi\Repository\MetaObjectRepositoryInterface;
use Bareapi\Schema\RefersToDefinition;
use Ramsey\Uuid\Uuid;

/**
 * Enriches responses with related object data.
 *
 * Provides optional relationship enrichment via query parameters:
 * - ?include=relationships - Include all declared relationships
 * - ?relationships=field1,field2 - Include only specific relationships
 */
final class RelationshipEnrichmentService
{
    public function __construct(
        private MetaObjectRepositoryInterface $metaObjectRepository,
        private SchemaServiceInterface $schemaService,
    ) {
    }

    /**
     * Enrich a response with relationships.
     *
     * @param array<string, mixed> $data Object data payload
     * @param string $objectType Object type
     * @param string[] $requestedPaths Optional filter for specific paths (empty = all)
     * @param int|null $projectId Project scope for lookups
     * @param string $organizationId Organization scope
     * @param string $baseUrl Base URL for link generation
     * @return array<string, array{data: array<string, mixed>|array<int, array<string, mixed>>|null, url: string|array<int, string>, meta: array{sourcePath: string}}>
     */
    public function enrichRelationships(
        array $data,
        string $objectType,
        array $requestedPaths,
        ?int $projectId,
        string $organizationId,
        string $baseUrl,
    ): array {
        $definitions = $this->schemaService->getRefersToDefinitions($objectType);

        if (empty($definitions)) {
            return [];
        }

        // Filter definitions by requested paths if specified
        if (! empty($requestedPaths)) {
            $definitions = array_filter(
                $definitions,
                fn (RefersToDefinition $def) => $this->matchesRequestedPath($def->path, $requestedPaths)
            );
        }

        $relationships = [];

        foreach ($definitions as $definition) {
            $uuids = $this->getValuesAtPath($data, $definition->path);

            if (empty($uuids)) {
                continue;
            }

            // Determine if this is an array field
            $isArray = $this->isArrayPath($data, $definition->path);

            if ($isArray) {
                $relationships[$definition->path] = $this->buildArrayRelationship(
                    $uuids,
                    $definition->targetType,
                    $definition->path,
                    $projectId,
                    $organizationId,
                    $baseUrl
                );
            } else {
                $relationships[$definition->path] = $this->buildSingleRelationship(
                    $uuids[0],
                    $definition->targetType,
                    $definition->path,
                    $projectId,
                    $organizationId,
                    $baseUrl
                );
            }
        }

        return $relationships;
    }

    /**
     * Build relationship entry for a single reference.
     *
     * @return array{data: array<string, mixed>|null, url: string, meta: array{sourcePath: string}}
     */
    private function buildSingleRelationship(
        string $uuid,
        string $targetType,
        string $path,
        ?int $projectId,
        string $organizationId,
        string $baseUrl,
    ): array {
        $related = $this->findRelatedObject($uuid, $targetType, $projectId, $organizationId);

        return [
            'data' => $related?->jsonSerialize(),
            'url' => $baseUrl . '/' . $targetType . '/' . $uuid,
            'meta' => [
                'sourcePath' => $path,
            ],
        ];
    }

    /**
     * Build relationship entry for array of references.
     *
     * @param string[] $uuids
     * @return array{data: array<int, array<string, mixed>>, url: array<int, string>, meta: array{sourcePath: string}}
     */
    private function buildArrayRelationship(
        array $uuids,
        string $targetType,
        string $path,
        ?int $projectId,
        string $organizationId,
        string $baseUrl,
    ): array {
        $data = [];
        $urls = [];

        foreach (array_unique($uuids) as $uuid) {
            if (! Uuid::isValid($uuid)) {
                continue;
            }

            $related = $this->findRelatedObject($uuid, $targetType, $projectId, $organizationId);
            if ($related !== null) {
                $data[] = $related->jsonSerialize();
                $urls[] = $baseUrl . '/' . $targetType . '/' . $uuid;
            }
        }

        return [
            'data' => $data,
            'url' => $urls,
            'meta' => [
                'sourcePath' => $path,
            ],
        ];
    }

    private function findRelatedObject(
        string $uuid,
        string $targetType,
        ?int $projectId,
        string $organizationId,
    ): ?MetaObject {
        if (! Uuid::isValid($uuid)) {
            return null;
        }

        $object = $this->metaObjectRepository->findByUuidString($uuid);

        // Verify the object matches expected type and scope
        if ($object === null
            || $object->isDeleted()
            || $object->getObjectType() !== $targetType
            || $object->getOrganizationId() !== $organizationId) {
            return null;
        }

        // Check project scope
        if ($projectId !== null && $object->getProjectId() !== $projectId) {
            return null;
        }

        return $object;
    }

    /**
     * Check if a path matches any of the requested paths.
     *
     * @param string[] $requestedPaths
     */
    private function matchesRequestedPath(string $path, array $requestedPaths): bool
    {
        // Strip "data." prefix for comparison
        $normalizedPath = str_starts_with($path, 'data.') ? substr($path, 5) : $path;

        foreach ($requestedPaths as $requested) {
            if ($requested === $path || $requested === $normalizedPath) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a path represents an array field.
     *
     * @param array<string, mixed> $data
     */
    private function isArrayPath(array $data, string $path): bool
    {
        // Strip "data." prefix
        if (str_starts_with($path, 'data.')) {
            $path = substr($path, 5);
        }

        $segments = explode('.', $path);
        $current = $data;

        // Walk to the parent of the target field
        foreach ($segments as $i => $segment) {
            if (! is_array($current)) {
                return false;
            }

            // Check if current level is a list
            if (array_is_list($current)) {
                return true;
            }

            if (! isset($current[$segment])) {
                return false;
            }

            // For the last segment, check if the value is an array
            if ($i === count($segments) - 1) {
                return is_array($current[$segment]) && array_is_list($current[$segment]);
            }

            $current = $current[$segment];
        }

        return false;
    }

    /**
     * Get value(s) at a dotted path, handling arrays.
     *
     * @param array<string, mixed> $data
     * @return string[]
     */
    private function getValuesAtPath(array $data, string $path): array
    {
        // Strip "data." prefix
        if (str_starts_with($path, 'data.')) {
            $path = substr($path, 5);
        }

        return $this->extractValuesRecursive($data, explode('.', $path));
    }

    /**
     * @param string[] $segments
     * @return string[]
     */
    private function extractValuesRecursive(mixed $current, array $segments): array
    {
        if (empty($segments)) {
            if (is_string($current) && $current !== '') {
                return [$current];
            }
            if (is_array($current) && array_is_list($current)) {
                return array_filter($current, fn ($v) => is_string($v) && $v !== '');
            }

            return [];
        }

        if (! is_array($current)) {
            return [];
        }

        $segment = array_shift($segments);

        if (array_is_list($current)) {
            $values = [];
            foreach ($current as $item) {
                if (is_array($item) && isset($item[$segment])) {
                    $values = array_merge(
                        $values,
                        $this->extractValuesRecursive($item[$segment], $segments)
                    );
                }
            }

            return $values;
        }

        if (! isset($current[$segment])) {
            return [];
        }

        return $this->extractValuesRecursive($current[$segment], $segments);
    }
}
