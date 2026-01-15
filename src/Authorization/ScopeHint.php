<?php

declare(strict_types=1);

namespace Bareapi\Authorization;

class ScopeHint
{
    public function __construct(
        public readonly bool $isProjectScoped,
        public readonly bool $isOrgScoped,
    ) {
    }
}
