<?php

declare(strict_types=1);

namespace Bareapi\Exception;

use RuntimeException;

final class InvalidRefersToException extends RuntimeException
{
    public function __construct(
        private string $path,
        private string $reason,
    ) {
        parent::__construct("Invalid refersTo at '{$path}': {$reason}");
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}
