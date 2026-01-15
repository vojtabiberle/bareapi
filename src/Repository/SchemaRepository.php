<?php

declare(strict_types=1);

namespace Bareapi\Repository;

use Bareapi\Entity\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class SchemaRepository implements SchemaRepositoryInterface
{
    /**
     * @var EntityRepository<Schema>
     */
    private EntityRepository $repository;

    public function __construct(
        private EntityManagerInterface $em,
    ) {
        $this->repository = $em->getRepository(Schema::class);
    }

    public function findDefaultSchema(string $objectType): ?Schema
    {
        return $this->repository->findOneBy([
            'objectType' => $objectType,
            'isDefault' => true,
        ]);
    }

    public function findByVersion(string $objectType, string $version): ?Schema
    {
        return $this->repository->findOneBy([
            'objectType' => $objectType,
            'version' => $version,
        ]);
    }

    /**
     * @return Schema[]
     */
    public function findByObjectType(string $objectType): array
    {
        return $this->repository->findBy(
            [
                'objectType' => $objectType,
            ],
            [
                'version' => 'DESC',
            ]
        );
    }

    /**
     * @return array<int, string>
     */
    public function findAllObjectTypes(): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('DISTINCT s.objectType')
            ->from(Schema::class, 's')
            ->orderBy('s.objectType', 'ASC');

        /** @var array<int, array{objectType: string}> $results */
        $results = $qb->getQuery()->getArrayResult();

        return array_column($results, 'objectType');
    }

    public function save(Schema $schema): void
    {
        $this->em->persist($schema);
        $this->em->flush();
    }

    public function remove(Schema $schema): void
    {
        $this->em->remove($schema);
        $this->em->flush();
    }

    public function clearDefaultForObjectType(string $objectType): void
    {
        $qb = $this->em->createQueryBuilder();
        $qb->update(Schema::class, 's')
            ->set('s.isDefault', ':false')
            ->where('s.objectType = :objectType')
            ->setParameter('false', false)
            ->setParameter('objectType', $objectType);

        $qb->getQuery()->execute();
    }
}
