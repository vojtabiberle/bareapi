<?php

declare(strict_types=1);

namespace Bareapi\Repository;

use Bareapi\Entity\MetaRef;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Ramsey\Uuid\UuidInterface;

class MetaRefRepository implements MetaRefRepositoryInterface
{
    /**
     * @var EntityRepository<MetaRef>
     */
    private EntityRepository $repository;

    public function __construct(
        private EntityManagerInterface $em,
    ) {
        $this->repository = $em->getRepository(MetaRef::class);
    }

    /**
     * @return MetaRef[]
     */
    public function findInboundRefs(
        ?int $projectId,
        string $toType,
        UuidInterface $toUuid,
    ): array {
        $qb = $this->repository->createQueryBuilder('r')
            ->where('r.toType = :toType')
            ->andWhere('r.toUuid = :toUuid')
            ->setParameter('toType', $toType)
            ->setParameter('toUuid', $toUuid);

        if ($projectId !== null) {
            $qb->andWhere('r.projectId = :projectId')
                ->setParameter('projectId', $projectId);
        } else {
            $qb->andWhere('r.projectId IS NULL');
        }

        /** @var MetaRef[] $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * @return MetaRef[]
     */
    public function findOutboundRefs(
        ?int $projectId,
        string $fromType,
        UuidInterface $fromUuid,
    ): array {
        $qb = $this->repository->createQueryBuilder('r')
            ->where('r.fromType = :fromType')
            ->andWhere('r.fromUuid = :fromUuid')
            ->setParameter('fromType', $fromType)
            ->setParameter('fromUuid', $fromUuid);

        if ($projectId !== null) {
            $qb->andWhere('r.projectId = :projectId')
                ->setParameter('projectId', $projectId);
        } else {
            $qb->andWhere('r.projectId IS NULL');
        }

        /** @var MetaRef[] $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * @return array<int, array{fromType: string, path: string, count: int}>
     */
    public function countInboundRefsByPath(
        ?int $projectId,
        string $toType,
        UuidInterface $toUuid,
    ): array {
        $conn = $this->em->getConnection();

        $sql = <<<'SQL'
            SELECT from_type, path, COUNT(*) as count
            FROM meta_refs
            WHERE to_type = :toType
            AND to_uuid = :toUuid
        SQL;

        if ($projectId !== null) {
            $sql .= ' AND project_id = :projectId';
        } else {
            $sql .= ' AND project_id IS NULL';
        }

        $sql .= ' GROUP BY from_type, path';

        $params = [
            'toType' => $toType,
            'toUuid' => $toUuid->toString(),
        ];

        if ($projectId !== null) {
            $params['projectId'] = $projectId;
        }

        /** @var array<int, array{from_type: string, path: string, count: string}> $rows */
        $rows = $conn->fetchAllAssociative($sql, $params);

        return array_map(
            fn (array $row) => [
                'fromType' => $row['from_type'],
                'path' => $row['path'],
                'count' => (int) $row['count'],
            ],
            $rows
        );
    }

    public function deleteBySource(
        ?int $projectId,
        string $fromType,
        UuidInterface $fromUuid,
    ): int {
        $qb = $this->em->createQueryBuilder()
            ->delete(MetaRef::class, 'r')
            ->where('r.fromType = :fromType')
            ->andWhere('r.fromUuid = :fromUuid')
            ->setParameter('fromType', $fromType)
            ->setParameter('fromUuid', $fromUuid);

        if ($projectId !== null) {
            $qb->andWhere('r.projectId = :projectId')
                ->setParameter('projectId', $projectId);
        } else {
            $qb->andWhere('r.projectId IS NULL');
        }

        /** @var int $result */
        $result = $qb->getQuery()->execute();

        return $result;
    }

    /**
     * @param MetaRef[] $refs
     */
    public function batchInsert(array $refs): void
    {
        foreach ($refs as $ref) {
            $this->em->persist($ref);
        }
        $this->em->flush();
    }

    /**
     * @param MetaRef[] $newRefs
     */
    public function syncRefs(
        ?int $projectId,
        string $fromType,
        UuidInterface $fromUuid,
        array $newRefs,
    ): void {
        // Get existing refs
        $existingRefs = $this->findOutboundRefs($projectId, $fromType, $fromUuid);

        // Build key sets for comparison
        $existingKeys = [];
        foreach ($existingRefs as $ref) {
            $key = $this->buildRefKey($ref);
            $existingKeys[$key] = $ref;
        }

        $newKeys = [];
        foreach ($newRefs as $ref) {
            $key = $this->buildRefKey($ref);
            $newKeys[$key] = $ref;
        }

        // Delete refs that no longer exist
        foreach ($existingKeys as $key => $ref) {
            if (! isset($newKeys[$key])) {
                $this->em->remove($ref);
            }
        }

        // Insert new refs
        foreach ($newKeys as $key => $ref) {
            if (! isset($existingKeys[$key])) {
                $this->em->persist($ref);
            }
        }

        $this->em->flush();
    }

    public function save(MetaRef $ref): void
    {
        $this->em->persist($ref);
        $this->em->flush();
    }

    public function remove(MetaRef $ref): void
    {
        $this->em->remove($ref);
        $this->em->flush();
    }

    private function buildRefKey(MetaRef $ref): string
    {
        return sprintf(
            '%s|%s|%s|%s',
            $ref->getPath(),
            $ref->getToType(),
            $ref->getToUuid()->toString(),
            $ref->getProjectId() ?? 'null'
        );
    }
}
