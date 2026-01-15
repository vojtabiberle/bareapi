<?php

declare(strict_types=1);

namespace Bareapi\Repository;

use Bareapi\Entity\MetaObject;
use Bareapi\Entity\MetaObjectRevision;
use Bareapi\Exception\InvalidFilterException;
use Bareapi\Service\SchemaServiceInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class MetaObjectRepository implements MetaObjectRepositoryInterface
{
    /**
     * @var EntityRepository<MetaObject>
     */
    private EntityRepository $repository;

    /**
     * @var EntityRepository<MetaObjectRevision>
     */
    private EntityRepository $revisionRepository;

    public function __construct(
        private EntityManagerInterface $em,
        private SchemaServiceInterface $schemaService,
    ) {
        $this->repository = $em->getRepository(MetaObject::class);
        $this->revisionRepository = $em->getRepository(MetaObjectRevision::class);
    }

    public function findByUuid(UuidInterface $uuid): ?MetaObject
    {
        return $this->repository->findOneBy([
            'uuid' => $uuid,
        ]);
    }

    public function findByUuidString(string $uuid): ?MetaObject
    {
        try {
            $uuidObject = Uuid::fromString($uuid);

            return $this->findByUuid($uuidObject);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $filters
     * @return MetaObject[]
     */
    public function findByType(
        string $objectType,
        ?int $projectId,
        string $organizationId,
        string $branch = 'main',
        array $filters = [],
        int $limit = 100,
        int $offset = 0,
        bool $includeDeleted = false,
    ): array {
        // Validate filters against schema
        $filterableFields = $this->schemaService->getFilterableFields($objectType);
        foreach (array_keys($filters) as $key) {
            if (! in_array($key, $filterableFields, true)) {
                throw new InvalidFilterException((string) $key, $objectType);
            }
        }

        $conn = $this->em->getConnection();

        $sql = 'SELECT m.uuid FROM meta_objects m ';
        $sql .= 'LEFT JOIN meta_object_revisions r ON m.uuid = r.uuid AND r.deleted_at IS NULL ';
        $sql .= 'WHERE m.object_type = :objectType ';
        $sql .= 'AND m.organization_id = :organizationId ';
        $sql .= 'AND m.branch = :branch ';

        if ($projectId !== null) {
            $sql .= 'AND m.project_id = :projectId ';
        } else {
            $sql .= 'AND m.project_id IS NULL ';
        }

        if (! $includeDeleted) {
            $sql .= 'AND m.deleted_at IS NULL ';
        }

        // Add JSONB filters
        $paramIndex = 0;
        foreach ($filters as $key => $value) {
            $paramName = 'filter_' . $paramIndex;
            $sql .= "AND r.data->>'{$key}' = :{$paramName} ";
            $paramIndex++;
        }

        $sql .= 'GROUP BY m.uuid ';
        $sql .= 'ORDER BY m.last_updated DESC ';
        $sql .= 'LIMIT :limit OFFSET :offset';

        $params = [
            'objectType' => $objectType,
            'organizationId' => $organizationId,
            'branch' => $branch,
            'limit' => $limit,
            'offset' => $offset,
        ];

        if ($projectId !== null) {
            $params['projectId'] = $projectId;
        }

        $paramIndex = 0;
        foreach ($filters as $value) {
            $params['filter_' . $paramIndex] = is_scalar($value) ? (string) $value : '';
            $paramIndex++;
        }

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery($params)->fetchAllAssociative();

        $metaObjects = [];
        foreach ($result as $row) {
            if (isset($row['uuid']) && is_string($row['uuid'])) {
                $entity = $this->findByUuidString($row['uuid']);
                if ($entity !== null) {
                    $metaObjects[] = $entity;
                }
            }
        }

        return $metaObjects;
    }

    public function findByNameAndScope(
        string $objectType,
        string $name,
        string $branch,
        ?int $projectId,
        string $organizationId,
    ): ?MetaObject {
        $criteria = [
            'objectType' => $objectType,
            'name' => $name,
            'branch' => $branch,
            'organizationId' => $organizationId,
        ];

        if ($projectId !== null) {
            $criteria['projectId'] = $projectId;
        }

        return $this->repository->findOneBy($criteria);
    }

    public function findRevision(UuidInterface $uuid, int $revisionNumber): ?MetaObjectRevision
    {
        $metaObject = $this->findByUuid($uuid);
        if ($metaObject === null) {
            return null;
        }

        return $this->revisionRepository->findOneBy([
            'metaObject' => $metaObject,
            'revision' => $revisionNumber,
        ]);
    }

    /**
     * @return MetaObjectRevision[]
     */
    public function findRevisions(
        UuidInterface $uuid,
        bool $includeDeleted = false,
    ): array {
        $metaObject = $this->findByUuid($uuid);
        if ($metaObject === null) {
            return [];
        }

        $criteria = [
            'metaObject' => $metaObject,
        ];

        $revisions = $this->revisionRepository->findBy(
            $criteria,
            [
                'revision' => 'DESC',
            ]
        );

        if (! $includeDeleted) {
            $revisions = array_filter(
                $revisions,
                fn (MetaObjectRevision $r) => $r->getDeletedAt() === null
            );
        }

        return array_values($revisions);
    }

    /**
     * @return MetaObjectRevision[]
     */
    public function findRevisionsByType(
        string $objectType,
        ?int $projectId,
        string $organizationId,
        string $branch = 'main',
        int $limit = 100,
        int $offset = 0,
    ): array {
        $conn = $this->em->getConnection();

        $sql = 'SELECT r.id FROM meta_object_revisions r ';
        $sql .= 'JOIN meta_objects m ON r.uuid = m.uuid ';
        $sql .= 'WHERE m.object_type = :objectType ';
        $sql .= 'AND m.organization_id = :organizationId ';
        $sql .= 'AND m.branch = :branch ';
        $sql .= 'AND r.deleted_at IS NULL ';
        $sql .= 'AND m.deleted_at IS NULL ';

        if ($projectId !== null) {
            $sql .= 'AND m.project_id = :projectId ';
        } else {
            $sql .= 'AND m.project_id IS NULL ';
        }

        $sql .= 'ORDER BY r.created_at DESC ';
        $sql .= 'LIMIT :limit OFFSET :offset';

        $params = [
            'objectType' => $objectType,
            'organizationId' => $organizationId,
            'branch' => $branch,
            'limit' => $limit,
            'offset' => $offset,
        ];

        if ($projectId !== null) {
            $params['projectId'] = $projectId;
        }

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery($params)->fetchAllAssociative();

        $revisions = [];
        foreach ($result as $row) {
            if (isset($row['id']) && is_numeric($row['id'])) {
                $revision = $this->revisionRepository->find((int) $row['id']);
                if ($revision !== null) {
                    $revisions[] = $revision;
                }
            }
        }

        return $revisions;
    }

    public function save(MetaObject $metaObject): void
    {
        $this->em->persist($metaObject);
        $this->em->flush();
    }

    public function saveRevision(MetaObjectRevision $revision): void
    {
        $this->em->persist($revision);
        $this->em->flush();
    }

    public function softDelete(MetaObject $metaObject): void
    {
        $metaObject->setDeletedAt(new DateTimeImmutable());
        $this->em->flush();
    }

    public function softDeleteRevision(MetaObjectRevision $revision): void
    {
        $revision->setDeletedAt(new DateTimeImmutable());
        $this->em->flush();
    }

    public function remove(MetaObject $metaObject): void
    {
        $this->em->remove($metaObject);
        $this->em->flush();
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function count(
        string $objectType,
        ?int $projectId,
        string $organizationId,
        string $branch = 'main',
        array $filters = [],
        bool $includeDeleted = false,
    ): int {
        $filterableFields = $this->schemaService->getFilterableFields($objectType);
        foreach (array_keys($filters) as $key) {
            if (! in_array($key, $filterableFields, true)) {
                throw new InvalidFilterException((string) $key, $objectType);
            }
        }

        $conn = $this->em->getConnection();

        $sql = 'SELECT COUNT(DISTINCT m.uuid) as cnt FROM meta_objects m ';
        $sql .= 'LEFT JOIN meta_object_revisions r ON m.uuid = r.uuid AND r.deleted_at IS NULL ';
        $sql .= 'WHERE m.object_type = :objectType ';
        $sql .= 'AND m.organization_id = :organizationId ';
        $sql .= 'AND m.branch = :branch ';

        if ($projectId !== null) {
            $sql .= 'AND m.project_id = :projectId ';
        } else {
            $sql .= 'AND m.project_id IS NULL ';
        }

        if (! $includeDeleted) {
            $sql .= 'AND m.deleted_at IS NULL ';
        }

        $paramIndex = 0;
        foreach ($filters as $key => $value) {
            $paramName = 'filter_' . $paramIndex;
            $sql .= "AND r.data->>'{$key}' = :{$paramName} ";
            $paramIndex++;
        }

        $params = [
            'objectType' => $objectType,
            'organizationId' => $organizationId,
            'branch' => $branch,
        ];

        if ($projectId !== null) {
            $params['projectId'] = $projectId;
        }

        $paramIndex = 0;
        foreach ($filters as $value) {
            $params['filter_' . $paramIndex] = is_scalar($value) ? (string) $value : '';
            $paramIndex++;
        }

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery($params)->fetchAssociative();

        return isset($result['cnt']) && is_numeric($result['cnt']) ? (int) $result['cnt'] : 0;
    }
}
