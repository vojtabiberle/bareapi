<?php

declare(strict_types=1);

namespace Bareapi\Service;

use Bareapi\Entity\MetaObject;
use Bareapi\Exception\ReferenceDeleteRestrictedException;
use Bareapi\Exception\ReferenceValidationException;
use Bareapi\Exception\SchemaNotFoundException;
use Bareapi\Repository\MetaObjectRepository;
use Bareapi\Repository\MetaRefRepository;
use Bareapi\Repository\SchemaRepository;

final class ReferenceIntegrityService
{
    public function __construct(
        private SchemaRepository $schemaRepository,
        private MetaObjectRepository $metaObjectRepository,
        private MetaRefRepository $metaRefRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function replaceReferencesForObject(string $fromType, string $fromUuid, array $data): void
    {
        $this->metaRefRepository->replaceReferences($fromType, $fromUuid, $this->referencesForData($fromType, $data));
    }

    public function applyDeleteRules(MetaObject $object): void
    {
        $this->applyDeleteRulesFor($object, []);
        $this->metaRefRepository->deleteReferencesForObject($object->getObjectType(), $object->getId()->toString());
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, array{path: string, toType: string, toUuid: string}>
     */
    private function referencesForData(string $fromType, array $data): array
    {
        return array_values(array_filter(array_map(function (array $definition) use ($data): ?array {
            $value = $this->valueAtPath($data, $definition['property']);
            if ($value === null || $value === '') {
                return null;
            }
            if (! is_string($value)) {
                throw new ReferenceValidationException('Reference value must be a string');
            }

            $target = $this->metaObjectRepository->find($value);
            if (! $target instanceof MetaObject || $target->getObjectType() !== $definition['refersTo']) {
                throw new ReferenceValidationException('Referenced object not found');
            }

            return [
                'path' => $definition['property'],
                'toType' => $definition['refersTo'],
                'toUuid' => $value,
            ];
        }, $this->referenceDefinitions($fromType))));
    }

    /**
     * @param array<int, string> $visited
     */
    private function applyDeleteRulesFor(MetaObject $object, array $visited): void
    {
        $objectType = $object->getObjectType();
        $key = $objectType . ':' . $object->getId()->toString();
        if (in_array($key, $visited, true)) {
            return;
        }
        $visited[] = $key;

        foreach ($this->metaRefRepository->inboundReferences($objectType, $object->getId()->toString()) as $reference) {
            $onDelete = $this->onDeleteFor($reference['from_type'], $reference['path'], $objectType);
            if ($onDelete === 'restrict') {
                throw new ReferenceDeleteRestrictedException('Object has restrict references');
            }

            $dependent = $this->metaObjectRepository->find($reference['from_uuid']);
            if (! $dependent instanceof MetaObject) {
                continue;
            }

            $this->applyDeleteRulesFor($dependent, $visited);
            $this->metaObjectRepository->delete($dependent);
            $this->metaRefRepository->deleteReferencesForObject($dependent->getObjectType(), $dependent->getId()->toString());
        }
    }

    private function onDeleteFor(string $fromType, string $path, string $toType): string
    {
        foreach ($this->referenceDefinitions($fromType) as $definition) {
            if ($definition['property'] === $path && $definition['refersTo'] === $toType) {
                return $definition['onDelete'];
            }
        }

        return 'restrict';
    }

    /**
     * @return array<int, array{property: string, refersTo: string, onDelete: string}>
     */
    private function referenceDefinitions(string $objectType): array
    {
        try {
            $schema = $this->schemaRepository->getByObjectType($objectType)->getSchema();
        } catch (SchemaNotFoundException) {
            return [];
        }

        $raw = $schema['x-bareapi.references'] ?? null;
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter(array_map(static function (mixed $definition): ?array {
            if (! is_array($definition)) {
                return null;
            }

            $property = $definition['property'] ?? null;
            $refersTo = $definition['refersTo'] ?? null;
            $onDelete = $definition['onDelete'] ?? 'restrict';
            if (! is_string($property) || ! is_string($refersTo) || ! in_array($onDelete, ['restrict', 'cascade'], true)) {
                return null;
            }

            return [
                'property' => $property,
                'refersTo' => $refersTo,
                'onDelete' => $onDelete,
            ];
        }, $raw)));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function valueAtPath(array $data, string $path): mixed
    {
        $current = $data;
        foreach (explode('.', $path) as $part) {
            if (! is_array($current) || ! array_key_exists($part, $current)) {
                return null;
            }
            $current = $current[$part];
        }

        return $current;
    }
}
