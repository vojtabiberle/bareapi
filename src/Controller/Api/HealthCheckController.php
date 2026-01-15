<?php

declare(strict_types=1);

namespace Bareapi\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class HealthCheckController
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    #[Route('/health-check', name: 'health_check', methods: ['GET'])]
    public function healthCheck(): JsonResponse
    {
        $dbOk = $this->checkDatabase();

        $status = $dbOk ? 'healthy' : 'unhealthy';
        $statusCode = $dbOk ? 200 : 503;

        return new JsonResponse([
            'status' => $status,
            'checks' => [
                'database' => $dbOk ? 'ok' : 'error',
            ],
        ], $statusCode);
    }

    #[Route('/health-check/liveness', name: 'health_check_liveness', methods: ['GET'])]
    public function liveness(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
        ]);
    }

    #[Route('/health-check/readiness', name: 'health_check_readiness', methods: ['GET'])]
    public function readiness(): JsonResponse
    {
        $dbOk = $this->checkDatabase();

        if (! $dbOk) {
            return new JsonResponse([
                'status' => 'not_ready',
                'reason' => 'database_unavailable',
            ], 503);
        }

        return new JsonResponse([
            'status' => 'ready',
        ]);
    }

    private function checkDatabase(): bool
    {
        try {
            $this->em->getConnection()->executeQuery('SELECT 1');

            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
