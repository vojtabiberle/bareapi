<?php

declare(strict_types=1);

namespace Bareapi\Controller;

use Bareapi\Repository\MetaObjectRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class DataUpdateController
{
    public function __construct(
        private MetaObjectRepository $repo,
        private \Bareapi\Service\SchemaValidatorService $schemaValidator
    ) {
    }

    #[Route('/api/{type}/{id}', name: 'data_update', methods: ['PUT'])]
    public function __invoke(string $type, string $id, Request $request): JsonResponse
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $type)) {
            return new JsonResponse([
                'error' => 'Invalid type',
            ], 400);
        }
        $payloadRaw = json_decode($request->getContent(), true);
        /** @var array<string, mixed> $payload */
        $payload = is_array($payloadRaw) ? $payloadRaw : [];
        if (isset($payload['type']) && (! is_string($payload['type']) || ! preg_match('/^[A-Za-z0-9_]+$/', $payload['type']))) {
            return new JsonResponse([
                'error' => 'Invalid type',
            ], 400);
        }
        try {
            $validated = $this->schemaValidator->validate($type, $payload);
        } catch (\Bareapi\Exception\SchemaNotFoundException $e) {
            return new JsonResponse([
                'error' => 'Unknown type',
            ], 404);
        } catch (\Bareapi\Exception\ValidationException $e) {
            return new JsonResponse([
                'errors' => $e->getErrors(),
            ], 422);
        }

        $obj = $this->repo->find($id);
        if (! $obj || $obj->getType() !== $type) {
            return new JsonResponse([
                'error' => 'Not found',
            ], 404);
        }

        $obj->setData(ControllerUtil::toStringKeyedArray($validated));
        $this->repo->save($obj);

        return new JsonResponse($obj);
    }
}
