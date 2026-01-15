<?php

declare(strict_types=1);

namespace Bareapi\Authorization;

enum Role: string
{
    case OrganizationAdmin = 'organization-admin';
    case ProjectAdmin = 'project-admin';
}

enum Action: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Batch = 'batch';
}

enum Scope: string
{
    case Any = '*';
    case Organization = 'organization';
    case Project = 'project';
}
