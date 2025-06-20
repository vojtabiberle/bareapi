<?php

namespace Bareapi\Repository;

use Bareapi\Entity\MetaObject;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

class MetaObjectRepository
{
    private EntityManagerInterface $em;
    private string $entityClass;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->entityClass = MetaObject::class;
    }

    public function find(string $id): ?MetaObject
    {
        return $this->em->find($this->entityClass, $id);
    }

    public function findAllByType(string $type): array
    {
        return $this->createTypeQueryBuilder($type)
                    ->getQuery()
                    ->getResult();
    }

    public function findByTypeAndFilters(string $type, array $filters): array
    {
        $qb = $this->createTypeQueryBuilder($type);
        foreach ($filters as $key => $value) {
            $qb->andWhere("m.data->> :field = :val")
               ->setParameter('field', $key)
               ->setParameter('val', (string) $value);
        }
        return $qb->getQuery()->getResult();
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
