<?php

declare(strict_types=1);

namespace Bareapi\Exception;

use Bareapi\Entity\MetaObject;
use RuntimeException;

/**
 * Thrown when delete is blocked by restrict references.
 */
final class DeleteRestrictedException extends RuntimeException
{
    /**
     * @param array<int, array{fromType: string, path: string, count: int, sample: string[]}> $violations
     */
    public function __construct(
        private MetaObject $target,
        private array $violations,
    ) {
        $totalCount = array_sum(array_column($violations, 'count'));
        parent::__construct(
            "Cannot delete {$target->getObjectType()}/{$target->getUuid()}: " .
            "{$totalCount} object(s) reference this object"
        );
    }

    public function getTarget(): MetaObject
    {
        return $this->target;
    }

    /**
     * @return array<int, array{fromType: string, path: string, count: int, sample: string[]}>
     */
    public function getViolations(): array
    {
        return $this->violations;
    }
}
