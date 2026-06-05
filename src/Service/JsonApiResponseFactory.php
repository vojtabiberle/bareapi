<?php

declare(strict_types=1);

namespace Bareapi\Service;

use Bareapi\Entity\MetaObject;
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
                    'branch' => $branch,
                    'name' => $name,
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
    public function ok(MetaObject $object, array $attributes): JsonResponse
    {
        return new JsonResponse([
            'data' => $this->resource($object, $attributes),
        ]);
    }

    public function noContent(): JsonResponse
    {
        return new JsonResponse(null, 204);
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
    private function resource(MetaObject $object, array $attributes): array
    {
        return [
            'type' => $object->getType(),
            'id' => $object->getId()->toString(),
            'meta' => [
                'schemaVersion' => $object->getSchemaVersion(),
                'branch' => 'main',
                'name' => $this->nameFromAttributes($attributes),
                'lastUpdated' => $object->getUpdatedAt()->format(\DateTimeImmutable::ATOM),
                'createdAt' => $object->getCreatedAt()->format(\DateTimeImmutable::ATOM),
                'revision' => 1,
                'revisionCreatedAt' => $object->getCreatedAt()->format(\DateTimeImmutable::ATOM),
            ],
            'attributes' => $attributes,
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function nameFromAttributes(array $attributes): string
    {
        if (isset($attributes['name']) && is_string($attributes['name'])) {
            return $attributes['name'];
        }

        if (isset($attributes['title']) && is_string($attributes['title'])) {
            return $attributes['title'];
        }

        return '';
    }
}
