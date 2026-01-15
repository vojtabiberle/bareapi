<?php

declare(strict_types=1);

namespace Bareapi\Exception;

class UnauthorizedException extends \RuntimeException
{
    public function __construct(string $message = 'Unauthorized')
    {
        parent::__construct($message, 401);
    }
}
