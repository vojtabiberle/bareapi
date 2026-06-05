<?php

declare(strict_types=1);

namespace Bareapi\Controller;

use Bareapi\Entity\MetaObject;
use Bareapi\Repository\MetaObjectRepository;
use Bareapi\Repository\MetaObjectRevisionRepository;
use Bareapi\Service\JsonApiResponseFactory;
use Bareapi\Service\SchemaValidatorService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class RepositoryCreateController
{
    public function __construct(
        private MetaObjectRepository $repository,
        private MetaObjectRevisionRepository $revisionRepository,
        private SchemaValidatorService $schemaValidator,
        private JsonApiResponseFactory $responseFactory,
    ) {
    }

    #[Route('/api/v1/repository/{objectType}', name: 'repository_create', methods: ['POST'])]
    public function __invoke(string $objectType, Request $request): JsonResponse
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $objectType)) {
            return new JsonResponse([
                'error' => 'Invalid type',
            ], 400);
        }

        $payloadRaw = json_decode($request->getContent(), true);
        $payload = is_array($payloadRaw) ? ControllerUtil::toStringKeyedArray($payloadRaw) : [];
        $data = isset($payload['data']) && is_array($payload['data'])
            ? ControllerUtil::toStringKeyedArray($payload['data'])
            : [];

        try {
            $validated = $this->schemaValidator->validate($objectType, $data);
        } catch (\Bareapi\Exception\SchemaNotFoundException) {
            return new JsonResponse([
                'error' => 'Unknown type',
            ], 404);
        } catch (\Bareapi\Exception\ValidationException $e) {
            return new JsonResponse([
                'errors' => $e->getErrors(),
            ], 422);
        }

        $schemaVersion = isset($payload['schemaVersion']) && is_string($payload['schemaVersion'])
            ? $payload['schemaVersion']
            : '1.0';
        $name = isset($payload['name']) && is_string($payload['name'])
            ? $payload['name']
            : '';
        $branch = isset($payload['branch']) && is_string($payload['branch']) && trim($payload['branch']) !== ''
            ? $payload['branch']
            : 'main';

        $object = new MetaObject($objectType, $schemaVersion, ControllerUtil::toStringKeyedArray($validated), $name, $branch);
        $this->repository->save($object);
        $revision = $this->revisionRepository->createInitial($object, ControllerUtil::toStringKeyedArray($validated));

        return $this->responseFactory->created(
            $object,
            $name,
            $branch,
            $revision->getRevision(),
            ControllerUtil::toStringKeyedArray($validated)
        );
    }
}
