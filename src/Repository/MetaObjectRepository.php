<?php

namespace Bareapi\Repository;

use Bareapi\Controller\ControllerUtil;
use Bareapi\Entity\MetaObject;
use Bareapi\Exception\InvalidFilterException;
use Bareapi\Service\SchemaService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

class MetaObjectRepository
{
    private EntityManagerInterface $em;

    /**
     * @var class-string<MetaObject>
     */
    private string $entityClass;

    private SchemaService $schemaService;

    public function __construct(EntityManagerInterface $em, SchemaService $schemaService)
    {
        $this->em = $em;
        $this->entityClass = MetaObject::class;
        $this->schemaService = $schemaService;
    }

    public function find(string $id): ?MetaObject
    {
        $obj = $this->em->find($this->entityClass, $id);
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

        $sql = 'SELECT * FROM meta_objects WHERE type = :type';
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

    public function save(MetaObject $obj): void
    {
        $this->em->persist($obj);
        $this->em->flush();
    }

    public function delete(MetaObject $obj): void
    {
        $this->em->remove($obj);
        $this->em->flush();
    }

    private function createTypeQueryBuilder(string $type): QueryBuilder
    {
        $qb = $this->em->createQueryBuilder();
        return $qb->select('m')
            ->from($this->entityClass, 'm')
            ->where('m.type = :type')
            ->setParameter('type', $type);
    }
}
