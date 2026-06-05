<?php

declare(strict_types=1);

namespace Bareapi\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    public function __construct(
        private Connection $connection
    ) {
    }

    #[Route('/health-check', name: 'health_check', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        try {
            $this->connection->fetchOne('SELECT 1');
        } catch (\Throwable $e) {
            return new JsonResponse([
                'status' => 'error',
                'service' => 'BareAPI',
                'database' => 'unavailable',
                'error' => $e->getMessage(),
            ], 503);
        }

        return new JsonResponse([
            'status' => 'ok',
            'service' => 'BareAPI',
            'database' => 'ok',
        ]);
    }
}
