<?php

declare(strict_types=1);

namespace Bareapi\Repository;

use Bareapi\Controller\ControllerUtil;
use Bareapi\Entity\MetaObject;
use Bareapi\Exception\InvalidFilterException;
use Bareapi\Exception\SchemaNotFoundException;
use Bareapi\Service\FilterQuery;
use Bareapi\Service\SchemaServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

class MetaObjectRepository
{
    private EntityManagerInterface $em;

    /**
     * @var class-string<MetaObject>
     */
    private string $entityClass;

    private SchemaServiceInterface $schemaService;

    public function __construct(EntityManagerInterface $em, SchemaServiceInterface $schemaService)
    {
        $this->em = $em;
        $this->entityClass = MetaObject::class;
        $this->schemaService = $schemaService;
    }

    public function find(string $id): ?MetaObject
    {
        $obj = $this->em->find($this->entityClass, $id);
        if ($obj instanceof MetaObject && $obj->getDeletedAt() !== null) {
            return null;
        }

        return $obj instanceof MetaObject ? $obj : null;
    }

    /**
     * @return MetaObject[]
     */
    public function findAllByType(string $type): array
    {
        $result = $this->createTypeQueryBuilder($type)
            ->getQuery()
            ->getResult();
        return array_values(array_filter(
            is_array($result) ? $result : [],
            fn ($item) => $item instanceof \Bareapi\Entity\MetaObject
        ));
    }

    /**
     * @param array<string, mixed> $filters
     * @return MetaObject[]
     */
    public function findByTypeAndFilters(string $type, array $filters): array
    {
        $filterableFields = $this->schemaService->getFilterableFields($type);

        $sql = 'SELECT * FROM meta_objects WHERE type = :type AND deleted_at IS NULL';
        $params = [
            'type' => $type,
        ];
        $types = [
            'type' => \PDO::PARAM_STR,
        ];

        foreach ($filters as $key => $value) {
            if (! in_array($key, $filterableFields, true)) {
                throw new InvalidFilterException($key, $type);
            }
            $paramName = 'filter_' . $key;
            $sql .= " AND data->>'{$key}' = :{$paramName}";
            $params[$paramName] = ControllerUtil::toStringSafe($value);
        }

        // Remove duplicate AND if present
        $sql = preg_replace('/( AND )+/', ' AND ', $sql);
        if (! is_string($sql)) {
            throw new \RuntimeException('SQL must be a string');
        }

        $conn = $this->em->getConnection();
        $stmt = $conn->prepare($sql);

        // Bind parameters
        foreach ($params as $name => $val) {
            $stmt->bindValue($name, $val);
        }

        $result = $stmt->executeQuery()->fetchAllAssociative();

        // Hydrate MetaObject entities, skip nulls
        return array_values(array_filter(array_map(function ($row) {
            $entity = $this->em->getRepository(MetaObject::class)->find($row['id']);
            return $entity instanceof MetaObject ? $entity : null;
        }, $result)));
    }

    /**
     * @return array<int, MetaObjectListItem>
     * @throws InvalidFilterException
     * @throws SchemaNotFoundException
     */
    public function listRepositoryObjects(string $type, FilterQuery $query, \Bareapi\Repository\SchemaRepository $schemaRepository): array
    {
        $allowedTableFields = [
            'schema_version',
            'branch',
            'name',
            'last_updated',
            'created_at',
            'deleted_at',
            'revision',
            'revision_created_at',
        ];
        $filterableFields = null;
        $sql = <<<'SQL'
            SELECT mo.id, mor.revision, mor.data
            FROM meta_objects mo
            JOIN LATERAL (
                SELECT revision, data, created_at
                FROM meta_object_revisions
                WHERE uuid = mo.id AND deleted_at IS NULL
                ORDER BY revision DESC
                LIMIT 1
            ) mor ON TRUE
            WHERE mo.type = :type AND mo.deleted_at IS NULL
        SQL;
        $params = [
            'type' => $type,
        ];

        foreach ($query->filters() as $field => $value) {
            if (in_array($field, $allowedTableFields, true)) {
                $paramName = 'filter_' . str_replace('.', '_', $field);
                $sql .= sprintf(' AND %s = :%s', $this->tableColumn($field), $paramName);
                $params[$paramName] = $value;
                continue;
            }

            $filterableFields ??= $schemaRepository->filterableFields($type);
            if (! in_array($field, $filterableFields, true)) {
                throw new InvalidFilterException($field, $type);
            }

            $paramName = 'filter_' . str_replace('.', '_', $field);
            $sql .= sprintf(' AND mor.data #>> %s = :%s', $this->jsonPathLiteral($field), $paramName);
            $params[$paramName] = $value;
        }

        $orderBy = $query->orderBy();
        if ($orderBy !== null) {
            if (in_array($orderBy, $allowedTableFields, true)) {
                $sql .= sprintf(' ORDER BY %s %s', $this->tableColumn($orderBy), strtoupper($query->orderDirection()));
            } elseif (in_array($orderBy, $filterableFields ?? $schemaRepository->filterableFields($type), true)) {
                $sql .= sprintf(' ORDER BY mor.data #>> %s %s', $this->jsonPathLiteral($orderBy), strtoupper($query->orderDirection()));
            } else {
                throw new InvalidFilterException($orderBy, $type);
            }
        } else {
            $sql .= ' ORDER BY mo.created_at ASC';
        }

        if ($query->limit() !== null) {
            $sql .= ' LIMIT :limit';
            $params['limit'] = $query->limit();
        }

        if ($query->offset() > 0) {
            $sql .= ' OFFSET :offset';
            $params['offset'] = $query->offset();
        }

        return $this->hydrateListItems($this->em->getConnection()->fetchAllAssociative($sql, $params));
    }

    /**
     * @return array<int, MetaObjectListItem>
     * @throws InvalidFilterException
     * @throws SchemaNotFoundException
     */
    public function listRepositoryRevisions(string $type, FilterQuery $query, \Bareapi\Repository\SchemaRepository $schemaRepository): array
    {
        $allowedTableFields = [
            'schema_version',
            'branch',
            'name',
            'last_updated',
            'created_at',
            'deleted_at',
            'revision',
            'revision_created_at',
        ];
        $filterableFields = null;
        $sql = <<<'SQL'
            SELECT mo.id, mor.revision, mor.data
            FROM meta_objects mo
            JOIN meta_object_revisions mor ON mor.uuid = mo.id AND mor.deleted_at IS NULL
            WHERE mo.type = :type AND mo.deleted_at IS NULL
        SQL;
        $params = [
            'type' => $type,
        ];

        foreach ($query->filters() as $field => $value) {
            if (in_array($field, $allowedTableFields, true)) {
                $paramName = 'filter_' . str_replace('.', '_', $field);
                $sql .= sprintf(' AND %s = :%s', $this->tableColumn($field), $paramName);
                $params[$paramName] = $value;
                continue;
            }

            $filterableFields ??= $schemaRepository->filterableFields($type);
            if (! in_array($field, $filterableFields, true)) {
                throw new InvalidFilterException($field, $type);
            }

            $paramName = 'filter_' . str_replace('.', '_', $field);
            $sql .= sprintf(' AND mor.data #>> %s = :%s', $this->jsonPathLiteral($field), $paramName);
            $params[$paramName] = $value;
        }

        $orderBy = $query->orderBy();
        if ($orderBy !== null) {
            if (in_array($orderBy, $allowedTableFields, true)) {
                $sql .= sprintf(' ORDER BY %s %s', $this->tableColumn($orderBy), strtoupper($query->orderDirection()));
            } elseif (in_array($orderBy, $filterableFields ?? $schemaRepository->filterableFields($type), true)) {
                $sql .= sprintf(' ORDER BY mor.data #>> %s %s', $this->jsonPathLiteral($orderBy), strtoupper($query->orderDirection()));
            } else {
                throw new InvalidFilterException($orderBy, $type);
            }
        } else {
            $sql .= ' ORDER BY mo.created_at ASC, mor.revision ASC';
        }

        if ($query->limit() !== null) {
            $sql .= ' LIMIT :limit';
            $params['limit'] = $query->limit();
        }

        if ($query->offset() > 0) {
            $sql .= ' OFFSET :offset';
            $params['offset'] = $query->offset();
        }

        return $this->hydrateListItems($this->em->getConnection()->fetchAllAssociative($sql, $params));
    }

    public function save(MetaObject $obj): void
    {
        $this->em->persist($obj);
        $this->em->flush();
    }

    public function delete(MetaObject $obj): void
    {
        $obj->markDeleted(new \DateTimeImmutable());
        $this->em->flush();
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, MetaObjectListItem>
     */
    private function hydrateListItems(array $rows): array
    {
        return array_values(array_filter(array_map(function (array $row): ?MetaObjectListItem {
            $id = ControllerUtil::toStringSafe($row['id'] ?? '');
            $object = $this->find($id);
            if (! $object instanceof MetaObject) {
                return null;
            }

            $data = json_decode(ControllerUtil::toStringSafe($row['data'] ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
            $revision = (int) ControllerUtil::toStringSafe($row['revision'] ?? '1');

            return new MetaObjectListItem(
                $object,
                is_array($data) ? ControllerUtil::toStringKeyedArray($data) : [],
                $revision
            );
        }, $rows)));
    }

    private function createTypeQueryBuilder(string $type): QueryBuilder
    {
        $qb = $this->em->createQueryBuilder();
        return $qb->select('m')
            ->from($this->entityClass, 'm')
            ->where('m.type = :type')
            ->andWhere('m.deletedAt IS NULL')
            ->setParameter('type', $type);
    }

    private function tableColumn(string $field): string
    {
        return match ($field) {
            'revision' => 'mor.revision',
            'revision_created_at' => 'mor.created_at',
            default => 'mo.' . $field,
        };
    }

    private function jsonPathLiteral(string $field): string
    {
        return "'{" . implode(',', explode('.', $field)) . "}'";
    }
}
