<?php

declare(strict_types=1);

namespace Bareapi\Exception;

use RuntimeException;

final class SchemaNotFoundException extends RuntimeException
{
    private string $type;

    public function __construct(string $type)
    {
        parent::__construct(sprintf('Schema file for type "%s" not found', $type));
        $this->type = $type;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
