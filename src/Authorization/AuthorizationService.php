<?php

declare(strict_types=1);

namespace Bareapi\Authorization;

use Bareapi\Security\ApiKeyUser;
use Bareapi\Validation\AclParser;

class AuthorizationService
{
    public function __construct(
        private PolicyEvaluator $evaluator,
        private AclParser $aclParser,
    ) {
    }

    /**
     * @param array<string, mixed> $schemaData
     * @param array<string, mixed> $data
     */
    public function authorizeCreate(
        string $objectType,
        array $schemaData,
        ApiKeyUser $user,
        int $projectId,
        string $organizationId,
        array $data,
        string $requestedScope = '',
    ): void {
        $policy = $this->aclParser->parse($schemaData);

        if ($policy->isEmpty() || $policy->create === []) {
            return;
        }

        $request = new AuthorizationRequest(
            action: Action::Create,
            user: $user,
            objectContext: new ObjectContext(
                objectType: $objectType,
                projectId: (string) $projectId,
                organizationId: $organizationId,
            ),
            hint: new ScopeHint(
                isProjectScoped: $requestedScope === 'project' || $requestedScope === '',
                isOrgScoped: $requestedScope === 'organization',
            ),
            policy: $policy,
        );

        $this->evaluator->evaluate($request);
    }

    /**
     * @param array<string, mixed> $schemaData
     */
    public function authorizeExistingObjectAction(
        string $action,
        string $objectType,
        array $schemaData,
        ApiKeyUser $user,
        ?int $objectProjectId,
        string $objectOrganizationId,
    ): void {
        $policy = $this->aclParser->parse($schemaData);

        $actionEnum = Action::tryFrom($action);
        if ($actionEnum === null) {
            return;
        }

        $rules = $policy->getRulesFor($actionEnum);

        if ($rules === []) {
            return;
        }

        $request = new AuthorizationRequest(
            action: $actionEnum,
            user: $user,
            objectContext: new ObjectContext(
                objectType: $objectType,
                projectId: $objectProjectId !== null ? (string) $objectProjectId : '',
                organizationId: $objectOrganizationId,
            ),
            hint: new ScopeHint(
                isProjectScoped: $objectProjectId !== null,
                isOrgScoped: true,
            ),
            policy: $policy,
        );

        $this->evaluator->evaluate($request);
    }
}
