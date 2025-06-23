<?php

declare(strict_types=1);

namespace Bareapi\Exception;

final class InvalidFilterException extends \RuntimeException
{
    public function __construct(string $field, string $type)
    {
        parent::__construct(sprintf(
            'Filtering by field "%s" is not allowed for type "%s".',
            $field,
            $type
        ));
    }
}
