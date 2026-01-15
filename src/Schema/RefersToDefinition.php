<?php

declare(strict_types=1);

namespace Bareapi\Schema;

/**
 * Value object representing a refersTo definition from a schema field.
 */
final readonly class RefersToDefinition
{
    public function __construct(
        public string $path,
        public string $targetType,
        public string $targetField,
        public OnDeleteBehavior $onDelete,
    ) {
    }
}
