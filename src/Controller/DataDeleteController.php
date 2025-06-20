<?php

namespace App\Controller;

use App\Repository\MetaObjectRepository;
use Symfony\Component\HttpFoundation\JsonResponse;

class DataDeleteController
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

        $this->repo->delete($obj);
        return new JsonResponse(null, 204);
    }
}