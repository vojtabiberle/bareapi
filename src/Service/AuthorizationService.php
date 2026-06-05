<?php

declare(strict_types=1);

namespace Bareapi\Service;

use Bareapi\Exception\SchemaNotFoundException;
use Bareapi\Repository\SchemaRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class AuthorizationService
{
    public function __construct(
        private SchemaRepository $schemaRepository
    ) {
    }

    public function denyResponseForAction(string $objectType, string $action, Request $request): ?JsonResponse
    {
        $requiredPermissions = $this->requiredPermissions($objectType, $action);
        if ($requiredPermissions === []) {
            return null;
        }

        $permissions = $this->permissionsFromRequest($request);
        if ($permissions === []) {
            return new JsonResponse([
                'error' => 'Unauthorized',
            ], 401);
        }

        if (array_intersect($requiredPermissions, $permissions) === []) {
            return new JsonResponse([
                'error' => 'Forbidden',
            ], 403);
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function requiredPermissions(string $objectType, string $action): array
    {
        try {
            $schema = $this->schemaRepository->getByObjectType($objectType)->getSchema();
        } catch (SchemaNotFoundException) {
            return [];
        }

        $acl = $schema['x-bareapi.acl'] ?? null;
        if (! is_array($acl)) {
            return [];
        }

        $permissions = $acl[$action] ?? null;
        if (! is_array($permissions)) {
            return [];
        }

        return array_values(array_filter($permissions, static fn (mixed $permission): bool => is_string($permission)));
    }

    /**
     * @return array<int, string>
     */
    private function permissionsFromRequest(Request $request): array
    {
        $authorization = $request->headers->get('Authorization');
        if (! is_string($authorization) || ! str_starts_with($authorization, 'Bearer ')) {
            return [];
        }

        $token = trim(substr($authorization, 7));
        if ($token === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $token)),
            static fn (string $permission): bool => $permission !== ''
        ));
    }
}
