<?php

namespace Bareapi\Controller;

use Bareapi\Repository\MetaObjectRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class DataListController
{
    public function __construct(private MetaObjectRepository $repo)
    {
    }

    #[Route('/data/{type}', name: 'data_list', methods: ['GET'])]
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