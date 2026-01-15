<?php

declare(strict_types=1);

namespace Bareapi\Authorization;

use Bareapi\Exception\ForbiddenException;

class PolicyEvaluator
{
    public function evaluate(AuthorizationRequest $request): void
    {
        $request->policy->validate();

        $rules = $request->policy->getRulesFor($request->action);
        if ($rules === []) {
            return;
        }

        $userRoles = $this->mapUserRolesToAuthRoles($request->user->getRoles());
        $hint = $this->normalizeHint($request->objectContext, $request->hint);

        foreach ($rules as $rule) {
            if (! $this->hasAnyRole($userRoles, $rule->roles)) {
                continue;
            }

            if (! $this->scopeMatches($rule, $hint)) {
                continue;
            }

            if ($rule->when !== null) {
                if ($rule->when === 'false') {
                    continue;
                }
                if ($rule->when === "object.ProjectID != ''" && $request->objectContext->projectId === '') {
                    continue;
                }
            }

            return;
        }

        throw new ForbiddenException('No matching authorization rule');
    }

    /**
     * @param string[] $userRoles
     * @return Role[]
     */
    private function mapUserRolesToAuthRoles(array $userRoles): array
    {
        $mapped = [];
        foreach ($userRoles as $role) {
            $authRole = Role::tryFrom($role);
            if ($authRole !== null) {
                $mapped[] = $authRole;
            }
        }

        return $mapped;
    }

    /**
     * @param Role[] $userRoles
     * @param Role[] $requiredRoles
     */
    private function hasAnyRole(array $userRoles, array $requiredRoles): bool
    {
        foreach ($requiredRoles as $required) {
            if (in_array($required, $userRoles, true)) {
                return true;
            }
        }

        return false;
    }

    private function scopeMatches(Rule $rule, ScopeHint $hint): bool
    {
        foreach ($rule->scopes as $scope) {
            if ($scope === Scope::Any) {
                return true;
            }
            if ($scope === Scope::Organization && $hint->isOrgScoped) {
                return true;
            }
            if ($scope === Scope::Project && $hint->isProjectScoped) {
                return true;
            }
        }

        return false;
    }

    private function normalizeHint(ObjectContext $object, ScopeHint $hint): ScopeHint
    {
        return new ScopeHint(
            isProjectScoped: $hint->isProjectScoped || $object->projectId !== '',
            isOrgScoped: $hint->isOrgScoped || $object->organizationId !== '',
        );
    }
}
