<?php

declare(strict_types=1);

namespace Bareapi\Service;

use Bareapi\Entity\MetaObject;
use Bareapi\Repository\MetaObjectListItem;
use Symfony\Component\HttpFoundation\JsonResponse;

final class JsonApiResponseFactory
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function created(MetaObject $object, string $name, string $branch, int $revision, array $attributes): JsonResponse
    {
        return new JsonResponse([
            'data' => [
                'type' => $object->getType(),
                'id' => $object->getId()->toString(),
                'meta' => [
                    'schemaVersion' => $object->getSchemaVersion(),
                    'branch' => $object->getBranch(),
                    'name' => $object->getName(),
                    'lastUpdated' => $object->getUpdatedAt()->format(\DateTimeImmutable::ATOM),
                    'createdAt' => $object->getCreatedAt()->format(\DateTimeImmutable::ATOM),
                    'revision' => $revision,
                    'revisionCreatedAt' => $object->getCreatedAt()->format(\DateTimeImmutable::ATOM),
                ],
                'attributes' => $attributes,
            ],
        ], 201);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function ok(MetaObject $object, array $attributes, int $revision = 1): JsonResponse
    {
        return new JsonResponse([
            'data' => $this->resource($object, $attributes, $revision),
        ]);
    }

    public function noContent(): JsonResponse
    {
        return new JsonResponse(null, 204);
    }

    /**
     * @param array<int, MetaObjectListItem> $items
     */
    public function collection(array $items): JsonResponse
    {
        return new JsonResponse([
            'data' => array_map(
                fn (MetaObjectListItem $item): array => $this->resource($item->object(), $item->data(), $item->revision()),
                $items
            ),
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array{
     *   type: string,
     *   id: string,
     *   meta: array<string, mixed>,
     *   attributes: array<string, mixed>
     * }
     */
    private function resource(MetaObject $object, array $attributes, int $revision): array
    {
        return [
            'type' => $object->getType(),
            'id' => $object->getId()->toString(),
            'meta' => [
                'schemaVersion' => $object->getSchemaVersion(),
                'branch' => $object->getBranch(),
                'name' => $object->getName(),
                'lastUpdated' => $object->getUpdatedAt()->format(\DateTimeImmutable::ATOM),
                'createdAt' => $object->getCreatedAt()->format(\DateTimeImmutable::ATOM),
                'revision' => $revision,
                'revisionCreatedAt' => $object->getCreatedAt()->format(\DateTimeImmutable::ATOM),
            ],
            'attributes' => $attributes,
        ];
    }
}
