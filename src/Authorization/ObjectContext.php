<?php

declare(strict_types=1);

namespace Bareapi\Authorization;

class ObjectContext
{
    public function __construct(
        public readonly string $objectType,
        public readonly string $projectId,
        public readonly string $organizationId,
    ) {
    }
}
