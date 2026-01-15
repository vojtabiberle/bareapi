<?php

declare(strict_types=1);

namespace Bareapi\Authorization;

enum Action: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Batch = 'batch';
}
