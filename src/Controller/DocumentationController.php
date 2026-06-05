<?php

declare(strict_types=1);

namespace Bareapi\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class DocumentationController
{
    public function __construct(
        private string $projectDir
    ) {
    }

    #[Route('/api/v1/documentation/openapi.json', name: 'openapi_documentation', methods: ['GET'])]
    public function openApi(): JsonResponse
    {
        $path = $this->projectDir . '/docs/api/openapi.json';
        $document = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return new JsonResponse(is_array($document) ? $document : []);
    }
}
