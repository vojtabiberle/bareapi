<?php

declare(strict_types=1);

namespace Bareapi\Exception;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    /**
     * @var array<string, mixed>
     */
    private array $errors;

    /**
     * @param array<string, mixed> $errors
     */
    public function __construct(array $errors)
    {
        parent::__construct('Validation failed');
        $this->errors = $errors;
    }

    /**
     * @return array<string, mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
