<?php

declare(strict_types=1);

namespace Bareapi\Exception;

class MetaObjectNotFoundException extends \RuntimeException
{
    public function __construct(string $message = 'Meta object not found')
    {
        parent::__construct($message, 404);
    }
}
