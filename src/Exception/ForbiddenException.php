<?php

declare(strict_types=1);

namespace Bareapi\Exception;

class ForbiddenException extends \RuntimeException
{
    public function __construct(string $message = 'Forbidden')
    {
        parent::__construct($message, 403);
    }
}
