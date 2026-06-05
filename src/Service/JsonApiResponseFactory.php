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
}
