<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\Authorization;

use Bareapi\Authorization\Action;
use Bareapi\Authorization\Role;
use Bareapi\Authorization\Rule;
use Bareapi\Authorization\Scope;
use PHPUnit\Framework\TestCase;

final class RuleTest extends TestCase
{
    public function testConstructorStoresRolesCorrectly(): void
    {
        $roles = [Role::OrganizationAdmin, Role::ProjectAdmin];
        $scopes = [Scope::Any];

        $rule = new Rule($roles, $scopes);

        $this->assertSame($roles, $rule->roles);
    }

    public function testConstructorStoresScopesCorrectly(): void
    {
        $roles = [Role::OrganizationAdmin];
        $scopes = [Scope::Project, Scope::Organization];

        $rule = new Rule($roles, $scopes);

        $this->assertSame($scopes, $rule->scopes);
    }

    public function testConstructorStoresWhenCondition(): void
    {
        $rule = new Rule(
            roles: [Role::OrganizationAdmin],
            scopes: [Scope::Any],
            when: "object.ProjectID != ''"
        );

        $this->assertSame("object.ProjectID != ''", $rule->when);
    }

    public function testConstructorSetsWhenToNullByDefault(): void
    {
        $rule = new Rule(
            roles: [Role::OrganizationAdmin],
            scopes: [Scope::Any]
        );

        $this->assertNull($rule->when);
    }

    public function testValidateThrowsExceptionWhenRolesEmpty(): void
    {
        $rule = new Rule(
            roles: [],
            scopes: [Scope::Any]
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rule 0 for action "create": at least one role must be defined');

        $rule->validate(Action::Create, 0);
    }

    public function testValidateThrowsExceptionWhenScopesEmpty(): void
    {
        $rule = new Rule(
            roles: [Role::OrganizationAdmin],
            scopes: []
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rule 0 for action "update": at least one scope must be defined');

        $rule->validate(Action::Update, 0);
    }

    public function testValidateIncludesIndexInExceptionMessage(): void
    {
        $rule = new Rule(
            roles: [],
            scopes: [Scope::Any]
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rule 5 for action "delete"');

        $rule->validate(Action::Delete, 5);
    }

    public function testValidateSucceedsWhenBothRolesAndScopesPresent(): void
    {
        $rule = new Rule(
            roles: [Role::OrganizationAdmin],
            scopes: [Scope::Any]
        );

        $rule->validate(Action::Create, 0);
        $this->addToAssertionCount(1);
    }
}
