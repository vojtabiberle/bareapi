<?php

declare(strict_types=1);

namespace Bareapi\Authorization;

enum Scope: string
{
    case Any = '*';
    case Organization = 'organization';
    case Project = 'project';
}
