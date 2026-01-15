<?php

declare(strict_types=1);

namespace Bareapi\Exception;

use RuntimeException;

/**
 * Thrown when reference validation fails during create/update.
 *
 * Contains details about the invalid reference for error reporting.
 */
final class ReferenceValidationException extends RuntimeException
{
    /**
     * @param array{path: string, type: string, uuid: string} $ref
     */
    public function __construct(
        private array $ref,
        string $message = 'Referenced object not found',
    ) {
        parent::__construct($message);
    }

    /**
     * @return array{path: string, type: string, uuid: string}
     */
    public function getRef(): array
    {
        return $this->ref;
    }

    public function getPath(): string
    {
        return $this->ref['path'];
    }

    public function getType(): string
    {
        return $this->ref['type'];
    }

    public function getRefUuid(): string
    {
        return $this->ref['uuid'];
    }
}
