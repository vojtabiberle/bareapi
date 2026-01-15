<?php

declare(strict_types=1);

namespace Bareapi\Service;

use Doctrine\ORM\EntityManagerInterface;

class TransactionManager
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Execute a callback within a database transaction.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     * @throws \Throwable
     */
    public function transactional(callable $callback): mixed
    {
        $this->em->beginTransaction();

        try {
            $result = $callback();
            $this->em->flush();
            $this->em->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }
    }

    public function flush(): void
    {
        $this->em->flush();
    }

    public function persist(object $entity): void
    {
        $this->em->persist($entity);
    }

    public function remove(object $entity): void
    {
        $this->em->remove($entity);
    }

    public function clear(): void
    {
        $this->em->clear();
    }
}
