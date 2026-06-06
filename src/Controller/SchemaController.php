<?php

declare(strict_types=1);

namespace Bareapi\Controller;

use Bareapi\Exception\SchemaNotFoundException;
use Bareapi\Repository\SchemaRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class SchemaController
{
    public function __construct(
        private SchemaRepository $repository
    ) {
    }

    #[Route('/api/v1/schema/{objectType}', name: 'schema_default', methods: ['GET'])]
    #[Route('/api/v1/schema/{objectType}/{version}', name: 'schema_version', methods: ['GET'])]
    public function __invoke(string $objectType, Request $request, ?string $version = null): JsonResponse
    {
        try {
            $schema = $this->repository->getByObjectType($objectType, $version);
        } catch (SchemaNotFoundException) {
            return new JsonResponse([
                'error' => 'Schema not found',
            ], 404);
        }

        $schemaDocument = $schema->getSchema();
        $schemaDocument['$id'] = sprintf(
            '%s/api/v1/schema/%s/%s',
            $request->getSchemeAndHttpHost(),
            $schema->getObjectType(),
            $schema->getVersion(),
        );

        return new JsonResponse($schemaDocument);
    }
}
