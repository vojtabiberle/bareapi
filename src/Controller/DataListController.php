<?php

namespace App\Controller;

use App\Repository\MetaObjectRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class DataListController
{
    public function __construct(private MetaObjectRepository $repo)
    {
    }

    public function __invoke(string $type, Request $request): JsonResponse
    {
        $filters = $request->query->all();
        if ($filters) {
            $data = $this->repo->findByTypeAndFilters($type, $filters);
        } else {
            $data = $this->repo->findAllByType($type);
        }

        return new JsonResponse($data);
    }
}