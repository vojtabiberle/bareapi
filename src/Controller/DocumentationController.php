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
        if (! is_readable($path)) {
            return new JsonResponse([
                'error' => 'OpenAPI document not found',
            ], 404);
        }

        $contents = file_get_contents($path);
        if (! is_string($contents)) {
            return new JsonResponse([
                'error' => 'OpenAPI document cannot be read',
            ], 500);
        }

        try {
            $document = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse([
                'error' => 'OpenAPI document is invalid',
            ], 500);
        }

        return new JsonResponse(is_array($document) ? $document : []);
    }
}
