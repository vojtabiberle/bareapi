<?php

namespace Bareapi\Controller;

use Bareapi\Repository\MetaObjectRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

class DataShowController
{
    public function __construct(private MetaObjectRepository $repo)
    {
    }

    public function __invoke(string $type, string $id): JsonResponse
    {
        $obj = $this->repo->find($id);
        if (!$obj || $obj->getType() !== $type) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        return new JsonResponse($obj);
    }
}