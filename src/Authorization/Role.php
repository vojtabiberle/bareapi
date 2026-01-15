<?php

declare(strict_types=1);

namespace Bareapi\Authorization;

enum Role: string
{
    case OrganizationAdmin = 'organization-admin';
    case ProjectAdmin = 'project-admin';
}
