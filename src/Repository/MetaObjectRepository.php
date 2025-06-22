<?php

namespace Bareapi\Repository;

use Bareapi\Entity\MetaObject;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Bareapi\Controller\ControllerUtil;

class MetaObjectRepository
{
    private EntityManagerInterface $em;
    /** @var class-string<MetaObject> */
    private string $entityClass;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->entityClass = MetaObject::class;
    }

    /**
     * @return MetaObject|null
     */
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
            fn($item) => $item instanceof \Bareapi\Entity\MetaObject
        ));
    }

    /**
     * @param array<string, mixed> $filters
     * @return MetaObject[]
     */
    public function findByTypeAndFilters(string $type, array $filters): array
    {
        $qb = $this->createTypeQueryBuilder($type);
        foreach ($filters as $key => $value) {
            $qb->andWhere("m.data->> :field = :val")
                ->setParameter('field', $key)
                ->setParameter('val', ControllerUtil::toStringSafe($value));
        }
        $result = $qb->getQuery()->getResult();
        return array_values(array_filter(
            is_array($result) ? $result : [],
            fn($item) => $item instanceof \Bareapi\Entity\MetaObject
        ));
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
