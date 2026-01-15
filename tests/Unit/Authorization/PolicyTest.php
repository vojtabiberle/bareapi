<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\Authorization;

use Bareapi\Authorization\Action;
use Bareapi\Authorization\Policy;
use Bareapi\Authorization\Role;
use Bareapi\Authorization\Rule;
use Bareapi\Authorization\Scope;
use PHPUnit\Framework\TestCase;

final class PolicyTest extends TestCase
{
    public function testGetRulesForReturnsCreateRulesForCreateAction(): void
    {
        $createRule = new Rule([Role::OrganizationAdmin], [Scope::Any]);
        $policy = new Policy(create: [$createRule]);

        $result = $policy->getRulesFor(Action::Create);

        $this->assertSame([$createRule], $result);
    }

    public function testGetRulesForReturnsUpdateRulesForUpdateAction(): void
    {
        $updateRule = new Rule([Role::ProjectAdmin], [Scope::Project]);
        $policy = new Policy(update: [$updateRule]);

        $result = $policy->getRulesFor(Action::Update);

        $this->assertSame([$updateRule], $result);
    }

    public function testGetRulesForReturnsDeleteRulesForDeleteAction(): void
    {
        $deleteRule = new Rule([Role::OrganizationAdmin], [Scope::Organization]);
        $policy = new Policy(delete: [$deleteRule]);

        $result = $policy->getRulesFor(Action::Delete);

        $this->assertSame([$deleteRule], $result);
    }

    public function testGetRulesForReturnsEmptyArrayForBatchAction(): void
    {
        $policy = new Policy(
            create: [new Rule([Role::OrganizationAdmin], [Scope::Any])],
            update: [new Rule([Role::OrganizationAdmin], [Scope::Any])],
            delete: [new Rule([Role::OrganizationAdmin], [Scope::Any])]
        );

        $result = $policy->getRulesFor(Action::Batch);

        $this->assertSame([], $result);
    }

    public function testIsEmptyReturnsTrueWhenAllArraysEmpty(): void
    {
        $policy = new Policy();

        $this->assertTrue($policy->isEmpty());
    }

    public function testIsEmptyReturnsFalseWhenCreateHasRules(): void
    {
        $policy = new Policy(
            create: [new Rule([Role::OrganizationAdmin], [Scope::Any])]
        );

        $this->assertFalse($policy->isEmpty());
    }

    public function testIsEmptyReturnsFalseWhenUpdateHasRules(): void
    {
        $policy = new Policy(
            update: [new Rule([Role::ProjectAdmin], [Scope::Project])]
        );

        $this->assertFalse($policy->isEmpty());
    }

    public function testIsEmptyReturnsFalseWhenDeleteHasRules(): void
    {
        $policy = new Policy(
            delete: [new Rule([Role::OrganizationAdmin], [Scope::Organization])]
        );

        $this->assertFalse($policy->isEmpty());
    }

    public function testValidateCallsValidateOnEachRuleForEachAction(): void
    {
        $createRule = new Rule([Role::OrganizationAdmin], [Scope::Any]);
        $updateRule = new Rule([Role::ProjectAdmin], [Scope::Project]);
        $deleteRule = new Rule([Role::OrganizationAdmin], [Scope::Organization]);

        $policy = new Policy(
            create: [$createRule],
            update: [$updateRule],
            delete: [$deleteRule]
        );

        $policy->validate();
        $this->addToAssertionCount(1);
    }

    public function testValidateSkipsEmptyRuleArrays(): void
    {
        $policy = new Policy();

        $policy->validate();
        $this->addToAssertionCount(1);
    }

    public function testValidatePropagatesExceptionFromRuleValidate(): void
    {
        $invalidRule = new Rule([], [Scope::Any]);
        $policy = new Policy(create: [$invalidRule]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rule 0 for action "create": at least one role must be defined');

        $policy->validate();
    }

    public function testValidateReportsCorrectIndexForMultipleRules(): void
    {
        $validRule = new Rule([Role::OrganizationAdmin], [Scope::Any]);
        $invalidRule = new Rule([Role::ProjectAdmin], []);
        $policy = new Policy(update: [$validRule, $invalidRule]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rule 1 for action "update": at least one scope must be defined');

        $policy->validate();
    }

    public function testConstructorDefaultsToEmptyArrays(): void
    {
        $policy = new Policy();

        $this->assertSame([], $policy->create);
        $this->assertSame([], $policy->update);
        $this->assertSame([], $policy->delete);
    }
}
