<?php

namespace Bareapi\Controller;

use Bareapi\Repository\MetaObjectRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class DataListController
{
    public function __construct(
        private MetaObjectRepository $repo
    ) {
    }

    #[Route('/api/{type}', name: 'data_list', methods: ['GET'])]
    public function __invoke(string $type, Request $request): JsonResponse
    {
        $filters = $request->query->all();
        try {
            if ($filters) {
                $data = $this->repo->findByTypeAndFilters($type, ControllerUtil::toStringKeyedArray($filters));
            } else {
                $data = $this->repo->findAllByType($type);
            }
            return new JsonResponse($data);
        } catch (\Bareapi\Exception\InvalidFilterException $e) {
            return new JsonResponse([
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Bareapi\Exception\SchemaNotFoundException $e) {
            return new JsonResponse([
                'message' => $e->getMessage(),
            ], 404);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
