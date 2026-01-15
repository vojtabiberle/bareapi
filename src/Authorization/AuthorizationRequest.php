<?php

declare(strict_types=1);

namespace Bareapi\Authorization;

use Bareapi\Security\ApiKeyUser;

class AuthorizationRequest
{
    public function __construct(
        public readonly Action $action,
        public readonly ApiKeyUser $user,
        public readonly ObjectContext $objectContext,
        public readonly ScopeHint $hint,
        public readonly Policy $policy,
    ) {
    }
}
