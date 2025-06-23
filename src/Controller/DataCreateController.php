<?php

namespace Bareapi\Controller;

use Bareapi\Entity\MetaObject;
use Bareapi\Repository\MetaObjectRepository;
use Bareapi\Controller\ControllerUtil;
use JsonSchema\Validator;
use JsonSchema\Constraints\Constraint;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class DataCreateController
{
    public function __construct(
        private MetaObjectRepository $repo,
        private \Bareapi\Service\SchemaValidatorService $schemaValidator
    ) {}

    #[Route('/api/{type}', name: 'data_create', methods: ['POST'])]
    public function __invoke(string $type, Request $request): JsonResponse
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $type)) {
            return new JsonResponse(['error' => 'Invalid type'], 400);
        }
        $payloadRaw = json_decode($request->getContent(), true);
        /** @var array<string, mixed> $payload */
        $payload = is_array($payloadRaw) ? $payloadRaw : [];
        if (isset($payload['type']) && (!is_string($payload['type']) || !preg_match('/^[A-Za-z0-9_]+$/', $payload['type']))) {
            return new JsonResponse(['error' => 'Invalid type'], 400);
        }
        try {
            $validated = $this->schemaValidator->validate($type, $payload);
        } catch (\Bareapi\Exception\SchemaNotFoundException $e) {
            return new JsonResponse(['error' => 'Unknown type'], 404);
        } catch (\Bareapi\Exception\ValidationException $e) {
            return new JsonResponse(['errors' => $e->getErrors()], 422);
        }

        $version = isset($validated['version']) && is_string($validated['version'])
            ? ControllerUtil::toStringSafe($validated['version'])
            : '1.0';

        $obj = new MetaObject(
            $type,
            $version,
            ControllerUtil::toStringKeyedArray($validated)
        );
        $this->repo->save($obj);

        return new JsonResponse($obj, 201);
    }
}
