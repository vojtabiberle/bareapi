<?php

declare(strict_types=1);

namespace Bareapi\Service;

use Doctrine\ORM\EntityManagerInterface;

final class TransactionManager
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function transactional(callable $operation): mixed
    {
        return $this->entityManager->wrapInTransaction(static fn (): mixed => $operation());
    }
}
