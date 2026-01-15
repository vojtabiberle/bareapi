<?php

declare(strict_types=1);

namespace Bareapi\Controller\Api;

use Bareapi\Exception\SchemaNotFoundException;
use Bareapi\Service\SchemaServiceInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/schema')]
class SchemaController
{
    public function __construct(
        private SchemaServiceInterface $schemaService,
    ) {
    }

    #[Route('/{objectType}', name: 'schema_get_default', methods: ['GET'])]
    public function getDefault(string $objectType): JsonResponse
    {
        try {
            $schema = $this->schemaService->getDefaultSchema($objectType);

            return new JsonResponse([
                'data' => [
                    'type' => 'schemas',
                    'id' => sprintf('%s-%s', $schema->getObjectType(), $schema->getVersion()),
                    'attributes' => [
                        'objectType' => $schema->getObjectType(),
                        'version' => $schema->getVersion(),
                        'isDefault' => $schema->isDefault(),
                        'description' => $schema->getDescription(),
                        'schema' => $schema->getSchema(),
                        'createdAt' => $schema->getCreatedAt()->format(\DateTimeInterface::RFC3339),
                        'updatedAt' => $schema->getUpdatedAt()->format(\DateTimeInterface::RFC3339),
                    ],
                ],
            ], headers: [
                'Content-Type' => 'application/vnd.api+json',
            ]);
        } catch (SchemaNotFoundException $e) {
            return new JsonResponse([
                'errors' => [[
                    'status' => '404',
                    'title' => 'Not Found',
                    'detail' => $e->getMessage(),
                ]],
            ], 404, [
                'Content-Type' => 'application/vnd.api+json',
            ]);
        }
    }

    #[Route('/{objectType}/{version}', name: 'schema_get_version', methods: ['GET'])]
    public function getVersion(string $objectType, string $version): JsonResponse
    {
        try {
            $schema = $this->schemaService->getSchemaByVersion($objectType, $version);

            return new JsonResponse([
                'data' => [
                    'type' => 'schemas',
                    'id' => sprintf('%s-%s', $schema->getObjectType(), $schema->getVersion()),
                    'attributes' => [
                        'objectType' => $schema->getObjectType(),
                        'version' => $schema->getVersion(),
                        'isDefault' => $schema->isDefault(),
                        'description' => $schema->getDescription(),
                        'schema' => $schema->getSchema(),
                        'createdAt' => $schema->getCreatedAt()->format(\DateTimeInterface::RFC3339),
                        'updatedAt' => $schema->getUpdatedAt()->format(\DateTimeInterface::RFC3339),
                    ],
                ],
            ], headers: [
                'Content-Type' => 'application/vnd.api+json',
            ]);
        } catch (SchemaNotFoundException $e) {
            return new JsonResponse([
                'errors' => [[
                    'status' => '404',
                    'title' => 'Not Found',
                    'detail' => $e->getMessage(),
                ]],
            ], 404, [
                'Content-Type' => 'application/vnd.api+json',
            ]);
        }
    }

    #[Route('', name: 'schema_list_types', methods: ['GET'])]
    public function listTypes(): JsonResponse
    {
        $types = $this->schemaService->listObjectTypes();

        return new JsonResponse([
            'data' => array_map(fn (string $type) => [
                'type' => 'object-types',
                'id' => $type,
                'attributes' => [
                    'name' => $type,
                ],
            ], $types),
        ], headers: [
            'Content-Type' => 'application/vnd.api+json',
        ]);
    }
}
