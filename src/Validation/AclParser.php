<?php

declare(strict_types=1);

namespace Bareapi\Validation;

use Bareapi\Authorization\Policy;
use Bareapi\Authorization\Role;
use Bareapi\Authorization\Rule;
use Bareapi\Authorization\Scope;

class AclParser
{
    private const ACL_EXTENSION_KEY = 'x-metastore.acl';

    private const ACL_NESTED_KEY = 'x-metastore';

    /**
     * @param array<string, mixed> $schemaData
     */
    public function parse(array $schemaData): Policy
    {
        $aclData = $schemaData[self::ACL_EXTENSION_KEY] ?? null;

        if ($aclData === null) {
            $xMetastore = $schemaData[self::ACL_NESTED_KEY] ?? null;
            if (is_array($xMetastore) && isset($xMetastore['acl'])) {
                $aclData = $xMetastore['acl'];
            }
        }

        if (! is_array($aclData) || $aclData === []) {
            return new Policy();
        }

        return new Policy(
            create: $this->parseRules($aclData['create'] ?? []),
            update: $this->parseRules($aclData['update'] ?? []),
            delete: $this->parseRules($aclData['delete'] ?? []),
        );
    }

    /**
     * @return Rule[]
     */
    private function parseRules(mixed $rawRules): array
    {
        if (! is_array($rawRules)) {
            return [];
        }

        $rules = [];

        foreach ($rawRules as $rawRule) {
            if (! is_array($rawRule)) {
                continue;
            }

            /** @var array<string, mixed> $rawRuleTyped */
            $rawRuleTyped = $rawRule;
            $roles = $this->parseRoles($rawRuleTyped);
            $scopes = $this->parseScopes($rawRuleTyped);

            $rules[] = new Rule(
                roles: $roles,
                scopes: $scopes,
                when: isset($rawRuleTyped['when']) && is_string($rawRuleTyped['when']) ? $rawRuleTyped['when'] : null,
            );
        }

        return $rules;
    }

    /**
     * @param array<string, mixed> $rawRule
     * @return Role[]
     */
    private function parseRoles(array $rawRule): array
    {
        $roles = [];

        if (isset($rawRule['roles']) && is_array($rawRule['roles'])) {
            foreach ($rawRule['roles'] as $role) {
                if (is_string($role)) {
                    $enumRole = Role::tryFrom($role);
                    if ($enumRole !== null) {
                        $roles[] = $enumRole;
                    }
                }
            }
        }

        if (isset($rawRule['role']) && is_string($rawRule['role'])) {
            $role = Role::tryFrom($rawRule['role']);
            if ($role !== null && ! in_array($role, $roles, true)) {
                $roles[] = $role;
            }
        }

        return $roles;
    }

    /**
     * @param array<string, mixed> $rawRule
     * @return Scope[]
     */
    private function parseScopes(array $rawRule): array
    {
        $scopes = [];

        if (isset($rawRule['scopes']) && is_array($rawRule['scopes'])) {
            foreach ($rawRule['scopes'] as $scope) {
                if (is_string($scope)) {
                    $enumScope = Scope::tryFrom($scope);
                    if ($enumScope !== null) {
                        $scopes[] = $enumScope;
                    }
                }
            }
        }

        if (isset($rawRule['scope']) && is_string($rawRule['scope'])) {
            $scope = Scope::tryFrom($rawRule['scope']);
            if ($scope !== null && ! in_array($scope, $scopes, true)) {
                $scopes[] = $scope;
            }
        }

        return $scopes;
    }
}
