<?php

declare(strict_types=1);

namespace Bareapi\Schema;

enum OnDeleteBehavior: string
{
    case Restrict = 'restrict';
    case Cascade = 'cascade';
}
