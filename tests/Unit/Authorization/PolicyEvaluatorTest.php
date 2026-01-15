<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\Authorization;

use Bareapi\Authorization\Action;
use Bareapi\Authorization\AuthorizationRequest;
use Bareapi\Authorization\ObjectContext;
use Bareapi\Authorization\Policy;
use Bareapi\Authorization\PolicyEvaluator;
use Bareapi\Authorization\Role;
use Bareapi\Authorization\Rule;
use Bareapi\Authorization\Scope;
use Bareapi\Authorization\ScopeHint;
use Bareapi\Exception\ForbiddenException;
use Bareapi\Security\ApiKeyUser;
use PHPUnit\Framework\TestCase;

final class PolicyEvaluatorTest extends TestCase
{
    private PolicyEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new PolicyEvaluator();
    }

    public function testAllowsAccessWhenNoRules(): void
    {
        $request = $this->createRequest(
            Action::Create,
            [],
            new Policy()
        );

        $this->evaluator->evaluate($request);
        $this->addToAssertionCount(1);
    }

    public function testAllowsAccessWhenRoleMatches(): void
    {
        $rule = new Rule(
            roles: [Role::OrganizationAdmin],
            scopes: [Scope::Any]
        );

        $policy = new Policy(create: [$rule]);

        $request = $this->createRequest(
            Action::Create,
            ['organization-admin'],
            $policy
        );

        $this->evaluator->evaluate($request);
        $this->addToAssertionCount(1);
    }

    public function testDeniesAccessWhenNoMatchingRole(): void
    {
        $rule = new Rule(
            roles: [Role::OrganizationAdmin],
            scopes: [Scope::Any]
        );

        $policy = new Policy(create: [$rule]);

        $request = $this->createRequest(
            Action::Create,
            ['project-admin'],
            $policy
        );

        $this->expectException(ForbiddenException::class);
        $this->evaluator->evaluate($request);
    }

    public function testAllowsAccessWhenScopeMatchesProject(): void
    {
        $rule = new Rule(
            roles: [Role::ProjectAdmin],
            scopes: [Scope::Project]
        );

        $policy = new Policy(update: [$rule]);

        $request = $this->createRequest(
            Action::Update,
            ['project-admin'],
            $policy,
            new ScopeHint(isProjectScoped: true, isOrgScoped: false)
        );

        $this->evaluator->evaluate($request);
        $this->addToAssertionCount(1);
    }

    public function testDeniesAccessWhenScopeDoesNotMatch(): void
    {
        $rule = new Rule(
            roles: [Role::ProjectAdmin],
            scopes: [Scope::Project]
        );

        $policy = new Policy(update: [$rule]);

        $request = $this->createRequest(
            Action::Update,
            ['project-admin'],
            $policy,
            new ScopeHint(isProjectScoped: false, isOrgScoped: true)
        );

        $this->expectException(ForbiddenException::class);
        $this->evaluator->evaluate($request);
    }

    public function testAllowsAccessWithOrganizationScope(): void
    {
        $rule = new Rule(
            roles: [Role::OrganizationAdmin],
            scopes: [Scope::Organization]
        );

        $policy = new Policy(delete: [$rule]);

        $request = $this->createRequest(
            Action::Delete,
            ['organization-admin'],
            $policy,
            new ScopeHint(isProjectScoped: false, isOrgScoped: true)
        );

        $this->evaluator->evaluate($request);
        $this->addToAssertionCount(1);
    }

    public function testSkipsRuleWithWhenFalse(): void
    {
        $rule = new Rule(
            roles: [Role::OrganizationAdmin],
            scopes: [Scope::Any],
            when: 'false'
        );

        $policy = new Policy(create: [$rule]);

        $request = $this->createRequest(
            Action::Create,
            ['organization-admin'],
            $policy
        );

        $this->expectException(ForbiddenException::class);
        $this->evaluator->evaluate($request);
    }

    public function testHandlesProjectIdCondition(): void
    {
        $rule = new Rule(
            roles: [Role::ProjectAdmin],
            scopes: [Scope::Project],
            when: "object.ProjectID != ''"
        );

        $policy = new Policy(update: [$rule]);

        $request = $this->createRequest(
            Action::Update,
            ['project-admin'],
            $policy,
            new ScopeHint(isProjectScoped: true, isOrgScoped: false),
            new ObjectContext('test', '', 'org-1')
        );

        $this->expectException(ForbiddenException::class);
        $this->evaluator->evaluate($request);
    }

    /**
     * @param string[] $roles
     */
    private function createRequest(
        Action $action,
        array $roles,
        Policy $policy,
        ?ScopeHint $hint = null,
        ?ObjectContext $objectContext = null,
    ): AuthorizationRequest {
        $user = new ApiKeyUser('test-key', $roles);

        return new AuthorizationRequest(
            action: $action,
            user: $user,
            objectContext: $objectContext ?? new ObjectContext('test', '123', 'org-1'),
            hint: $hint ?? new ScopeHint(isProjectScoped: true, isOrgScoped: true),
            policy: $policy
        );
    }
}
