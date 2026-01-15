<?php

declare(strict_types=1);

namespace Bareapi\Controller\Api;

use Bareapi\Authorization\AuthorizationService;
use Bareapi\DTO\CreateRequest;
use Bareapi\DTO\MetaObjectResponse;
use Bareapi\DTO\UpdatePatchRequest;
use Bareapi\DTO\UpdatePutRequest;
use Bareapi\Entity\MetaObject;
use Bareapi\Entity\MetaObjectRevision;
use Bareapi\Exception\DeleteRestrictedException;
use Bareapi\Exception\ForbiddenException;
use Bareapi\Exception\MetaObjectNotFoundException;
use Bareapi\Exception\ReferenceValidationException;
use Bareapi\Exception\SchemaNotFoundException;
use Bareapi\Exception\ValidationException;
use Bareapi\Repository\MetaObjectRepositoryInterface;
use Bareapi\Response\ErrorResponse;
use Bareapi\Response\JsonApiSerializer;
use Bareapi\Security\ApiKeyUser;
use Bareapi\Service\DeletePlannerService;
use Bareapi\Service\ReferenceIndexService;
use Bareapi\Service\RelationshipEnrichmentService;
use Bareapi\Service\SchemaServiceInterface;
use Bareapi\Service\TransactionManager;
use Bareapi\Validation\JsonSchemaValidator;
use Bareapi\Validation\ReferenceValidator;
use DateTimeImmutable;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/repository')]
class RepositoryController
{
    public function __construct(
        private MetaObjectRepositoryInterface $repository,
        private SchemaServiceInterface $schemaService,
        private JsonSchemaValidator $validator,
        private ReferenceValidator $referenceValidator,
        private ReferenceIndexService $referenceIndexService,
        private DeletePlannerService $deletePlannerService,
        private RelationshipEnrichmentService $relationshipEnrichmentService,
        private TransactionManager $transactionManager,
        private AuthorizationService $authService,
        private JsonApiSerializer $serializer,
        private Security $security,
    ) {
    }

    #[Route('/{objectType}', name: 'repository_list', methods: ['GET'])]
    public function list(string $objectType, Request $request): JsonResponse
    {
        try {
            if (! $this->schemaService->schemaExists($objectType)) {
                throw new SchemaNotFoundException("Schema not found for type: {$objectType}");
            }

            $projectId = $this->getProjectId($request);
            $organizationId = $this->getOrganizationId($request);
            $branch = $request->query->getString('branch', 'main');
            $limit = min(100, max(1, $request->query->getInt('limit', 100)));
            $offset = max(0, $request->query->getInt('offset', 0));

            $filters = $this->extractFilters($request, $objectType);

            $metaObjects = $this->repository->findByType(
                $objectType,
                $projectId,
                $organizationId,
                $branch,
                $filters,
                $limit,
                $offset
            );

            $responses = [];
            foreach ($metaObjects as $obj) {
                $latestRevision = $obj->getLatestRevision();
                if ($latestRevision !== null) {
                    $responses[] = MetaObjectResponse::fromEntity($obj, $latestRevision);
                }
            }

            return $this->serializer->success($responses);
        } catch (SchemaNotFoundException $e) {
            return ErrorResponse::notFound($e->getMessage());
        }
    }

    #[Route('/{objectType}', name: 'repository_create', methods: ['POST'])]
    public function create(string $objectType, Request $request): JsonResponse
    {
        try {
            $user = $this->getAuthenticatedUser();
            $body = $this->getJsonBody($request);
            $createRequest = CreateRequest::fromArray($body);

            $schema = $this->schemaService->getDefaultSchema($objectType);
            $schemaVersion = $createRequest->schemaVersion ?? $schema->getVersion();

            // Authorization check
            $this->authService->authorizeCreate(
                $objectType,
                $schema->getSchema(),
                $user,
                $this->getProjectId($request) ?? 0,
                $this->getOrganizationId($request),
                $createRequest->data,
                $createRequest->scope ?? ''
            );

            // Validate against schema
            $this->validator->validate($createRequest->data, $schema->getSchema());

            // Check for existing object with same name in scope
            $projectId = $this->getProjectIdFromScope($request, $createRequest->scope);

            // Validate references
            $this->referenceValidator->validate(
                $createRequest->data,
                $objectType,
                $projectId,
                $this->getOrganizationId($request)
            );
            $branch = $createRequest->branch ?? 'main';

            $existing = $this->repository->findByNameAndScope(
                $objectType,
                $createRequest->name,
                $branch,
                $projectId,
                $this->getOrganizationId($request)
            );

            if ($existing !== null) {
                return ErrorResponse::conflict(
                    "Object with name '{$createRequest->name}' already exists in this scope"
                );
            }

            $metaObject = $this->transactionManager->transactional(function () use (
                $objectType,
                $schemaVersion,
                $createRequest,
                $projectId,
                $request
            ) {
                $metaObject = new MetaObject(
                    $objectType,
                    $schemaVersion,
                    $createRequest->name,
                    $this->getOrganizationId($request)
                );
                $metaObject->setBranch($createRequest->branch ?? 'main');
                $metaObject->setProjectId($projectId);

                $revision = new MetaObjectRevision(
                    $metaObject,
                    1,
                    $createRequest->data
                );
                $metaObject->addRevision($revision);

                $this->transactionManager->persist($metaObject);

                return $metaObject;
            });

            // Sync reference index
            $this->referenceIndexService->syncRefsForObject($metaObject, $createRequest->data);

            $response = MetaObjectResponse::fromEntity($metaObject);

            return $this->serializer->created($response);
        } catch (SchemaNotFoundException $e) {
            return ErrorResponse::notFound($e->getMessage());
        } catch (ValidationException $e) {
            return ErrorResponse::validationErrorFromRaw($e->getErrors());
        } catch (ReferenceValidationException $e) {
            return ErrorResponse::referenceError($e->getRef());
        } catch (ForbiddenException $e) {
            return ErrorResponse::forbidden($e->getMessage());
        }
    }

    #[Route('/{objectType}/{uuid}', name: 'repository_get', methods: ['GET'])]
    public function get(string $objectType, string $uuid, Request $request): JsonResponse
    {
        try {
            $metaObject = $this->findOrFail($uuid, $objectType);
            $latestRevision = $metaObject->getLatestRevision();

            if ($latestRevision === null) {
                throw new MetaObjectNotFoundException('Object has no revisions');
            }

            $response = MetaObjectResponse::fromEntity($metaObject, $latestRevision);

            // Check for relationship enrichment
            $includeRelationships = $request->query->getBoolean('include')
                || $request->query->has('relationships');

            if ($includeRelationships) {
                $requestedPaths = $this->parseRelationshipsParam($request);
                $baseUrl = $this->getBaseApiUrl($request);

                $relationships = $this->relationshipEnrichmentService->enrichRelationships(
                    $latestRevision->getData(),
                    $objectType,
                    $requestedPaths,
                    $metaObject->getProjectId(),
                    $metaObject->getOrganizationId(),
                    $baseUrl
                );

                return $this->serializer->successWithRelationships($response, $relationships);
            }

            return $this->serializer->success($response);
        } catch (MetaObjectNotFoundException $e) {
            return ErrorResponse::notFound($e->getMessage());
        }
    }

    #[Route('/{objectType}/{uuid}', name: 'repository_patch', methods: ['PATCH'])]
    public function patch(string $objectType, string $uuid, Request $request): JsonResponse
    {
        try {
            $user = $this->getAuthenticatedUser();
            $metaObject = $this->findOrFail($uuid, $objectType);
            $latestRevision = $metaObject->getLatestRevision();

            if ($latestRevision === null) {
                throw new MetaObjectNotFoundException('Object has no revisions');
            }

            $body = $this->getJsonBody($request);
            $patchRequest = UpdatePatchRequest::fromArray($body);

            $schema = $this->schemaService->getDefaultSchema($objectType);

            // Authorization check
            $this->authService->authorizeExistingObjectAction(
                'update',
                $objectType,
                $schema->getSchema(),
                $user,
                $metaObject->getProjectId(),
                $metaObject->getOrganizationId()
            );

            // Merge existing data with patch data
            $mergedData = array_merge($latestRevision->getData(), $patchRequest->data);

            // Validate merged data against schema
            $this->validator->validate($mergedData, $schema->getSchema());

            // Validate references
            $this->referenceValidator->validate(
                $mergedData,
                $objectType,
                $metaObject->getProjectId(),
                $metaObject->getOrganizationId()
            );

            $metaObject = $this->transactionManager->transactional(function () use ($metaObject, $mergedData) {
                $newRevision = new MetaObjectRevision(
                    $metaObject,
                    $metaObject->getNextRevisionNumber(),
                    $mergedData
                );
                $newRevision->setParentId($metaObject->getUuid());

                $metaObject->addRevision($newRevision);
                $metaObject->setLastUpdated(new DateTimeImmutable());

                return $metaObject;
            });

            // Sync reference index
            $this->referenceIndexService->syncRefsForObject($metaObject, $mergedData);

            $response = MetaObjectResponse::fromEntity($metaObject);

            return $this->serializer->success($response);
        } catch (MetaObjectNotFoundException $e) {
            return ErrorResponse::notFound($e->getMessage());
        } catch (SchemaNotFoundException $e) {
            return ErrorResponse::notFound($e->getMessage());
        } catch (ValidationException $e) {
            return ErrorResponse::validationErrorFromRaw($e->getErrors());
        } catch (ReferenceValidationException $e) {
            return ErrorResponse::referenceError($e->getRef());
        } catch (ForbiddenException $e) {
            return ErrorResponse::forbidden($e->getMessage());
        }
    }

    #[Route('/{objectType}/{uuid}', name: 'repository_put', methods: ['PUT'])]
    public function put(string $objectType, string $uuid, Request $request): JsonResponse
    {
        try {
            $user = $this->getAuthenticatedUser();
            $metaObject = $this->findOrFail($uuid, $objectType);

            $body = $this->getJsonBody($request);
            $putRequest = UpdatePutRequest::fromArray($body);

            $schema = $this->schemaService->getDefaultSchema($objectType);
            $schemaVersion = $putRequest->schemaVersion ?? $schema->getVersion();

            // Authorization check
            $this->authService->authorizeExistingObjectAction(
                'update',
                $objectType,
                $schema->getSchema(),
                $user,
                $metaObject->getProjectId(),
                $metaObject->getOrganizationId()
            );

            // Validate data against schema
            $this->validator->validate($putRequest->data, $schema->getSchema());

            // Validate references
            $this->referenceValidator->validate(
                $putRequest->data,
                $objectType,
                $metaObject->getProjectId(),
                $metaObject->getOrganizationId()
            );

            $metaObject = $this->transactionManager->transactional(function () use (
                $metaObject,
                $putRequest,
                $schemaVersion
            ) {
                $newRevision = new MetaObjectRevision(
                    $metaObject,
                    $metaObject->getNextRevisionNumber(),
                    $putRequest->data
                );
                $newRevision->setParentId($metaObject->getUuid());

                $metaObject->addRevision($newRevision);
                $metaObject->setName($putRequest->name);
                $metaObject->setSchemaVersion($schemaVersion);
                if ($putRequest->branch !== null) {
                    $metaObject->setBranch($putRequest->branch);
                }
                $metaObject->setLastUpdated(new DateTimeImmutable());

                return $metaObject;
            });

            // Sync reference index
            $this->referenceIndexService->syncRefsForObject($metaObject, $putRequest->data);

            $response = MetaObjectResponse::fromEntity($metaObject);

            return $this->serializer->success($response);
        } catch (MetaObjectNotFoundException $e) {
            return ErrorResponse::notFound($e->getMessage());
        } catch (SchemaNotFoundException $e) {
            return ErrorResponse::notFound($e->getMessage());
        } catch (ValidationException $e) {
            return ErrorResponse::validationErrorFromRaw($e->getErrors());
        } catch (ReferenceValidationException $e) {
            return ErrorResponse::referenceError($e->getRef());
        } catch (ForbiddenException $e) {
            return ErrorResponse::forbidden($e->getMessage());
        }
    }

    #[Route('/{objectType}/{uuid}', name: 'repository_delete', methods: ['DELETE'])]
    public function delete(string $objectType, string $uuid, Request $request): JsonResponse
    {
        try {
            $user = $this->getAuthenticatedUser();
            $metaObject = $this->findOrFail($uuid, $objectType);

            $schema = $this->schemaService->getDefaultSchema($objectType);

            // Authorization check
            $this->authService->authorizeExistingObjectAction(
                'delete',
                $objectType,
                $schema->getSchema(),
                $user,
                $metaObject->getProjectId(),
                $metaObject->getOrganizationId()
            );

            // Execute delete with referential integrity checks
            $this->deletePlannerService->executeDelete($metaObject);

            return new JsonResponse(null, 204);
        } catch (MetaObjectNotFoundException $e) {
            return ErrorResponse::notFound($e->getMessage());
        } catch (SchemaNotFoundException $e) {
            return ErrorResponse::notFound($e->getMessage());
        } catch (DeleteRestrictedException $e) {
            return ErrorResponse::deleteRestricted($e->getViolations());
        } catch (ForbiddenException $e) {
            return ErrorResponse::forbidden($e->getMessage());
        }
    }

    #[Route('/{objectType}/revisions', name: 'repository_list_revisions', methods: ['GET'])]
    public function listRevisions(string $objectType, Request $request): JsonResponse
    {
        try {
            if (! $this->schemaService->schemaExists($objectType)) {
                throw new SchemaNotFoundException("Schema not found for type: {$objectType}");
            }

            $projectId = $this->getProjectId($request);
            $organizationId = $this->getOrganizationId($request);
            $branch = $request->query->getString('branch', 'main');
            $limit = min(100, max(1, $request->query->getInt('limit', 100)));
            $offset = max(0, $request->query->getInt('offset', 0));

            $revisions = $this->repository->findRevisionsByType(
                $objectType,
                $projectId,
                $organizationId,
                $branch,
                $limit,
                $offset
            );

            $data = array_map(fn (MetaObjectRevision $r) => [
                'type' => 'revisions',
                'id' => sprintf('%s-%d', $r->getMetaObject()->getUuid()->toString(), $r->getRevision()),
                'attributes' => [
                    'uuid' => $r->getMetaObject()->getUuid()->toString(),
                    'revision' => $r->getRevision(),
                    'data' => $r->getData(),
                    'createdAt' => $r->getCreatedAt()->format(\DateTimeInterface::RFC3339),
                ],
            ], $revisions);

            return new JsonResponse([
                'data' => $data,
            ], headers: [
                'Content-Type' => 'application/vnd.api+json',
            ]);
        } catch (SchemaNotFoundException $e) {
            return ErrorResponse::notFound($e->getMessage());
        }
    }

    #[Route('/{objectType}/{uuid}/revisions/{revision}', name: 'repository_get_revision', methods: ['GET'])]
    public function getRevision(string $objectType, string $uuid, int $revision): JsonResponse
    {
        try {
            $metaObject = $this->findOrFail($uuid, $objectType);
            $uuidObj = $metaObject->getUuid();

            $revisionEntity = $this->repository->findRevision($uuidObj, $revision);

            if ($revisionEntity === null || $revisionEntity->isDeleted()) {
                throw new MetaObjectNotFoundException("Revision {$revision} not found");
            }

            $response = MetaObjectResponse::fromEntity($metaObject, $revisionEntity);

            return $this->serializer->success($response);
        } catch (MetaObjectNotFoundException $e) {
            return ErrorResponse::notFound($e->getMessage());
        }
    }

    #[Route('/{objectType}/{uuid}/revisions/{revision}', name: 'repository_delete_revision', methods: ['DELETE'])]
    public function deleteRevision(string $objectType, string $uuid, int $revision, Request $request): JsonResponse
    {
        try {
            $user = $this->getAuthenticatedUser();
            $metaObject = $this->findOrFail($uuid, $objectType);

            $schema = $this->schemaService->getDefaultSchema($objectType);

            // Authorization check
            $this->authService->authorizeExistingObjectAction(
                'delete',
                $objectType,
                $schema->getSchema(),
                $user,
                $metaObject->getProjectId(),
                $metaObject->getOrganizationId()
            );

            $uuidObj = $metaObject->getUuid();
            $revisionEntity = $this->repository->findRevision($uuidObj, $revision);

            if ($revisionEntity === null) {
                throw new MetaObjectNotFoundException("Revision {$revision} not found");
            }

            $this->repository->softDeleteRevision($revisionEntity);

            return new JsonResponse(null, 204);
        } catch (MetaObjectNotFoundException $e) {
            return ErrorResponse::notFound($e->getMessage());
        } catch (SchemaNotFoundException $e) {
            return ErrorResponse::notFound($e->getMessage());
        } catch (ForbiddenException $e) {
            return ErrorResponse::forbidden($e->getMessage());
        }
    }

    private function findOrFail(string $uuid, string $objectType): MetaObject
    {
        $metaObject = $this->repository->findByUuidString($uuid);

        if ($metaObject === null || $metaObject->isDeleted()) {
            throw new MetaObjectNotFoundException("Object not found: {$uuid}");
        }

        if ($metaObject->getObjectType() !== $objectType) {
            throw new MetaObjectNotFoundException('Object type mismatch');
        }

        return $metaObject;
    }

    private function getAuthenticatedUser(): ApiKeyUser
    {
        $user = $this->security->getUser();

        if (! $user instanceof ApiKeyUser) {
            throw new ForbiddenException('Authentication required');
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function getJsonBody(Request $request): array
    {
        $content = $request->getContent();
        if ($content === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            return [];
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function getProjectId(Request $request): ?int
    {
        $projectId = $request->headers->get('X-Project-ID');

        return $projectId !== null && is_numeric($projectId) ? (int) $projectId : null;
    }

    private function getOrganizationId(Request $request): string
    {
        return $request->headers->get('X-Organization-ID') ?? '';
    }

    private function getProjectIdFromScope(Request $request, ?string $scope): ?int
    {
        if ($scope === 'organization') {
            return null;
        }

        return $this->getProjectId($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractFilters(Request $request, string $objectType): array
    {
        $filterableFields = $this->schemaService->getFilterableFields($objectType);
        $filters = [];

        foreach ($request->query->all() as $key => $value) {
            if (in_array($key, $filterableFields, true) && is_scalar($value)) {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }

    /**
     * Parse ?relationships=field1,field2 query param.
     *
     * @return string[]
     */
    private function parseRelationshipsParam(Request $request): array
    {
        $param = $request->query->getString('relationships', '');
        if ($param === '') {
            return []; // Empty means "all relationships"
        }

        return array_map('trim', explode(',', $param));
    }

    /**
     * Get base API URL for relationship links.
     */
    private function getBaseApiUrl(Request $request): string
    {
        return sprintf(
            '%s://%s/api/v1/repository',
            $request->getScheme(),
            $request->getHttpHost()
        );
    }
}
