<?php

namespace Bareapi\Controller;

use Bareapi\Repository\MetaObjectRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class DataShowController
{
    public function __construct(private MetaObjectRepository $repo) {}

    #[Route('/api/{type}/{id}', name: 'data_show', methods: ['GET'])]
    public function __invoke(string $type, string $id): JsonResponse
    {
        $obj = $this->repo->find($id);
        if (!$obj || $obj->getType() !== $type) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        return new JsonResponse($obj);
    }
}
