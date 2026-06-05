<?php

declare(strict_types=1);

namespace Bareapi\Controller;

use Bareapi\Exception\InvalidFilterException;
use Bareapi\Exception\SchemaNotFoundException;
use Bareapi\Repository\MetaObjectRepository;
use Bareapi\Repository\SchemaRepository;
use Bareapi\Service\FilterParser;
use Bareapi\Service\JsonApiResponseFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class RepositoryListController
{
    public function __construct(
        private MetaObjectRepository $repository,
        private SchemaRepository $schemaRepository,
        private FilterParser $filterParser,
        private JsonApiResponseFactory $responseFactory,
    ) {
    }

    #[Route('/api/v1/repository/{objectType}', name: 'repository_list', methods: ['GET'])]
    public function __invoke(string $objectType, Request $request): JsonResponse
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $objectType)) {
            return new JsonResponse([
                'error' => 'Invalid type',
            ], 400);
        }

        try {
            $query = $this->filterParser->parse($request);
            $items = $this->repository->listRepositoryObjects($objectType, $query, $this->schemaRepository);
        } catch (\InvalidArgumentException|InvalidFilterException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
            ], 400);
        } catch (SchemaNotFoundException) {
            return new JsonResponse([
                'error' => 'Unknown type',
            ], 404);
        }

        return $this->responseFactory->collection($items);
    }
}
