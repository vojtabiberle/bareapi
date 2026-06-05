<?php

declare(strict_types=1);

namespace Bareapi\Controller;

use Bareapi\Entity\MetaObject;
use Bareapi\Exception\ReferenceDeleteRestrictedException;
use Bareapi\Exception\ReferenceValidationException;
use Bareapi\Repository\MetaObjectRepository;
use Bareapi\Repository\MetaObjectRevisionRepository;
use Bareapi\Service\AuthorizationService;
use Bareapi\Service\JsonApiResponseFactory;
use Bareapi\Service\ReferenceIntegrityService;
use Bareapi\Service\SchemaValidatorService;
use Bareapi\Service\TransactionManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class RepositoryObjectController
{
    public function __construct(
        private MetaObjectRepository $repository,
        private MetaObjectRevisionRepository $revisionRepository,
        private AuthorizationService $authorizationService,
        private ReferenceIntegrityService $referenceIntegrityService,
        private TransactionManager $transactionManager,
        private SchemaValidatorService $schemaValidator,
        private JsonApiResponseFactory $responseFactory,
    ) {
    }

    #[Route('/api/v1/repository/{objectType}/{id}', name: 'repository_show', methods: ['GET'])]
    public function show(string $objectType, string $id): JsonResponse
    {
        $object = $this->findObject($objectType, $id);
        if (! $object instanceof MetaObject) {
            return new JsonResponse([
                'error' => 'Not found',
            ], 404);
        }

        return $this->responseFactory->ok(
            $object,
            $object->getData(),
            max(1, $this->revisionRepository->latestRevisionNumber($object->getId()->toString()))
        );
    }

    #[Route('/api/v1/repository/{objectType}/{id}/revisions/{revision}', name: 'repository_revision', methods: ['GET'])]
    public function revision(string $objectType, string $id, int $revision): JsonResponse
    {
        $object = $this->findObject($objectType, $id);
        if (! $object instanceof MetaObject) {
            return new JsonResponse([
                'error' => 'Not found',
            ], 404);
        }

        try {
            $objectRevision = $this->revisionRepository->get($object->getId()->toString(), $revision);
        } catch (\Throwable) {
            return new JsonResponse([
                'error' => 'Not found',
            ], 404);
        }

        return $this->responseFactory->ok($object, $objectRevision->getData(), $objectRevision->getRevision());
    }

    #[Route('/api/v1/repository/{objectType}/{id}/revisions/{revision}', name: 'repository_revision_delete', methods: ['DELETE'])]
    public function deleteRevision(string $objectType, string $id, int $revision, Request $request): JsonResponse
    {
        $object = $this->findObject($objectType, $id);
        if (! $object instanceof MetaObject) {
            return new JsonResponse([
                'error' => 'Not found',
            ], 404);
        }

        $denied = $this->authorizationService->denyResponseForAction($objectType, 'delete', $request);
        if ($denied instanceof JsonResponse) {
            return $denied;
        }

        $this->revisionRepository->softDelete($object->getId()->toString(), $revision);

        return $this->responseFactory->noContent();
    }

    #[Route('/api/v1/repository/{objectType}/{id}', name: 'repository_patch', methods: ['PATCH'])]
    public function patch(string $objectType, string $id, Request $request): JsonResponse
    {
        $object = $this->findObject($objectType, $id);
        if (! $object instanceof MetaObject) {
            return new JsonResponse([
                'error' => 'Not found',
            ], 404);
        }

        $denied = $this->authorizationService->denyResponseForAction($objectType, 'update', $request);
        if ($denied instanceof JsonResponse) {
            return $denied;
        }

        $payload = $this->requestPayload($request);
        $patchData = isset($payload['data']) && is_array($payload['data'])
            ? ControllerUtil::toStringKeyedArray($payload['data'])
            : [];
        $merged = ControllerUtil::toStringKeyedArray(array_replace_recursive($object->getData(), $patchData));

        return $this->updateObject($objectType, $object, $merged);
    }

    #[Route('/api/v1/repository/{objectType}/{id}', name: 'repository_put', methods: ['PUT'])]
    public function put(string $objectType, string $id, Request $request): JsonResponse
    {
        $object = $this->findObject($objectType, $id);
        if (! $object instanceof MetaObject) {
            return new JsonResponse([
                'error' => 'Not found',
            ], 404);
        }

        $denied = $this->authorizationService->denyResponseForAction($objectType, 'update', $request);
        if ($denied instanceof JsonResponse) {
            return $denied;
        }

        $payload = $this->requestPayload($request);
        $replacement = isset($payload['data']) && is_array($payload['data'])
            ? ControllerUtil::toStringKeyedArray($payload['data'])
            : [];

        return $this->updateObject($objectType, $object, $replacement);
    }

    #[Route('/api/v1/repository/{objectType}/{id}', name: 'repository_delete', methods: ['DELETE'])]
    public function delete(string $objectType, string $id, Request $request): JsonResponse
    {
        $object = $this->findObject($objectType, $id);
        if (! $object instanceof MetaObject) {
            return new JsonResponse([
                'error' => 'Not found',
            ], 404);
        }

        $denied = $this->authorizationService->denyResponseForAction($objectType, 'delete', $request);
        if ($denied instanceof JsonResponse) {
            return $denied;
        }

        try {
            $this->transactionManager->transactional(function () use ($object): void {
                $this->referenceIntegrityService->applyDeleteRules($object);
                $this->repository->delete($object);
            });
        } catch (ReferenceDeleteRestrictedException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
            ], 409);
        }

        return $this->responseFactory->noContent();
    }

    private function findObject(string $objectType, string $id): ?MetaObject
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $objectType)) {
            return null;
        }

        $object = $this->repository->find($id);
        if (! $object instanceof MetaObject || $object->getType() !== $objectType) {
            return null;
        }

        return $object;
    }

    /**
     * @return array<string, mixed>
     */
    private function requestPayload(Request $request): array
    {
        $decoded = json_decode($request->getContent(), true);

        return ControllerUtil::toStringKeyedArray($decoded);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function updateObject(string $objectType, MetaObject $object, array $data): JsonResponse
    {
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

        $attributes = ControllerUtil::toStringKeyedArray($validated);
        try {
            $revision = $this->transactionManager->transactional(function () use ($object, $objectType, $attributes): \Bareapi\Entity\MetaObjectRevision {
                $this->referenceIntegrityService->replaceReferencesForObject($objectType, $object->getId()->toString(), $attributes);
                $object->setData($attributes);
                $object->setUpdatedAt(new \DateTimeImmutable());
                $this->repository->save($object);

                return $this->revisionRepository->createNext($object, $attributes);
            });
        } catch (ReferenceValidationException $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
            ], 422);
        }

        return $this->responseFactory->ok($object, $attributes, $revision->getRevision());
    }
}
