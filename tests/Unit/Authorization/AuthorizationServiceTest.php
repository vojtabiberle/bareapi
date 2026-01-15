<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\Authorization;

use Bareapi\Authorization\Action;
use Bareapi\Authorization\AuthorizationRequest;
use Bareapi\Authorization\AuthorizationService;
use Bareapi\Authorization\Policy;
use Bareapi\Authorization\PolicyEvaluator;
use Bareapi\Authorization\Role;
use Bareapi\Authorization\Rule;
use Bareapi\Authorization\Scope;
use Bareapi\Security\ApiKeyUser;
use Bareapi\Validation\AclParser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AuthorizationServiceTest extends TestCase
{
    private PolicyEvaluator&MockObject $evaluator;

    private AclParser&MockObject $aclParser;

    private AuthorizationService $service;

    protected function setUp(): void
    {
        $this->evaluator = $this->createMock(PolicyEvaluator::class);
        $this->aclParser = $this->createMock(AclParser::class);
        $this->service = new AuthorizationService($this->evaluator, $this->aclParser);
    }

    public function testAuthorizeCreateReturnsEarlyWhenPolicyIsEmpty(): void
    {
        $this->aclParser->expects($this->once())
            ->method('parse')
            ->willReturn(new Policy());

        $this->evaluator->expects($this->never())
            ->method('evaluate');

        $this->service->authorizeCreate(
            objectType: 'notes',
            schemaData: [],
            user: new ApiKeyUser('test-key', ['organization-admin']),
            projectId: 123,
            organizationId: 'org-1',
            data: [
                'title' => 'Test',
            ]
        );

        $this->addToAssertionCount(1);
    }

    public function testAuthorizeCreateReturnsEarlyWhenCreateRulesEmpty(): void
    {
        $policy = new Policy(
            update: [new Rule([Role::OrganizationAdmin], [Scope::Any])]
        );

        $this->aclParser->expects($this->once())
            ->method('parse')
            ->willReturn($policy);

        $this->evaluator->expects($this->never())
            ->method('evaluate');

        $this->service->authorizeCreate(
            objectType: 'notes',
            schemaData: [],
            user: new ApiKeyUser('test-key', ['organization-admin']),
            projectId: 123,
            organizationId: 'org-1',
            data: [
                'title' => 'Test',
            ]
        );

        $this->addToAssertionCount(1);
    }

    public function testAuthorizeCreateDelegatesToEvaluatorWhenRulesExist(): void
    {
        $rule = new Rule([Role::OrganizationAdmin], [Scope::Any]);
        $policy = new Policy(create: [$rule]);

        $this->aclParser->expects($this->once())
            ->method('parse')
            ->willReturn($policy);

        $this->evaluator->expects($this->once())
            ->method('evaluate')
            ->with($this->isInstanceOf(AuthorizationRequest::class));

        $this->service->authorizeCreate(
            objectType: 'notes',
            schemaData: [],
            user: new ApiKeyUser('test-key', ['organization-admin']),
            projectId: 123,
            organizationId: 'org-1',
            data: [
                'title' => 'Test',
            ]
        );
    }

    public function testAuthorizeCreateConstructsCorrectRequestWithProjectScope(): void
    {
        $rule = new Rule([Role::OrganizationAdmin], [Scope::Any]);
        $policy = new Policy(create: [$rule]);

        $this->aclParser->expects($this->once())
            ->method('parse')
            ->willReturn($policy);

        $capturedRequest = null;
        $this->evaluator->expects($this->once())
            ->method('evaluate')
            ->with($this->callback(function (AuthorizationRequest $request) use (&$capturedRequest) {
                $capturedRequest = $request;
                return true;
            }));

        $this->service->authorizeCreate(
            objectType: 'notes',
            schemaData: [],
            user: new ApiKeyUser('test-key', ['organization-admin']),
            projectId: 123,
            organizationId: 'org-1',
            data: [
                'title' => 'Test',
            ],
            requestedScope: 'project'
        );

        $this->assertSame(Action::Create, $capturedRequest->action);
        $this->assertSame('notes', $capturedRequest->objectContext->objectType);
        $this->assertSame('123', $capturedRequest->objectContext->projectId);
        $this->assertSame('org-1', $capturedRequest->objectContext->organizationId);
        $this->assertTrue($capturedRequest->hint->isProjectScoped);
        $this->assertFalse($capturedRequest->hint->isOrgScoped);
    }

    public function testAuthorizeCreateConstructsCorrectRequestWithOrganizationScope(): void
    {
        $rule = new Rule([Role::OrganizationAdmin], [Scope::Any]);
        $policy = new Policy(create: [$rule]);

        $this->aclParser->expects($this->once())
            ->method('parse')
            ->willReturn($policy);

        $capturedRequest = null;
        $this->evaluator->expects($this->once())
            ->method('evaluate')
            ->with($this->callback(function (AuthorizationRequest $request) use (&$capturedRequest) {
                $capturedRequest = $request;
                return true;
            }));

        $this->service->authorizeCreate(
            objectType: 'notes',
            schemaData: [],
            user: new ApiKeyUser('test-key', ['organization-admin']),
            projectId: 123,
            organizationId: 'org-1',
            data: [
                'title' => 'Test',
            ],
            requestedScope: 'organization'
        );

        $this->assertFalse($capturedRequest->hint->isProjectScoped);
        $this->assertTrue($capturedRequest->hint->isOrgScoped);
    }

    public function testAuthorizeCreateDefaultsToProjectScopeWhenEmpty(): void
    {
        $rule = new Rule([Role::OrganizationAdmin], [Scope::Any]);
        $policy = new Policy(create: [$rule]);

        $this->aclParser->expects($this->once())
            ->method('parse')
            ->willReturn($policy);

        $capturedRequest = null;
        $this->evaluator->expects($this->once())
            ->method('evaluate')
            ->with($this->callback(function (AuthorizationRequest $request) use (&$capturedRequest) {
                $capturedRequest = $request;
                return true;
            }));

        $this->service->authorizeCreate(
            objectType: 'notes',
            schemaData: [],
            user: new ApiKeyUser('test-key', ['organization-admin']),
            projectId: 123,
            organizationId: 'org-1',
            data: [
                'title' => 'Test',
            ],
            requestedScope: ''
        );

        $this->assertTrue($capturedRequest->hint->isProjectScoped);
        $this->assertFalse($capturedRequest->hint->isOrgScoped);
    }

    public function testAuthorizeExistingObjectActionReturnsEarlyWhenActionInvalid(): void
    {
        $this->aclParser->expects($this->once())
            ->method('parse')
            ->willReturn(new Policy());

        $this->evaluator->expects($this->never())
            ->method('evaluate');

        $this->service->authorizeExistingObjectAction(
            action: 'invalid-action',
            objectType: 'notes',
            schemaData: [],
            user: new ApiKeyUser('test-key', ['organization-admin']),
            objectProjectId: 123,
            objectOrganizationId: 'org-1'
        );

        $this->addToAssertionCount(1);
    }

    public function testAuthorizeExistingObjectActionReturnsEarlyWhenNoRulesForAction(): void
    {
        $policy = new Policy(
            create: [new Rule([Role::OrganizationAdmin], [Scope::Any])]
        );

        $this->aclParser->expects($this->once())
            ->method('parse')
            ->willReturn($policy);

        $this->evaluator->expects($this->never())
            ->method('evaluate');

        $this->service->authorizeExistingObjectAction(
            action: 'update',
            objectType: 'notes',
            schemaData: [],
            user: new ApiKeyUser('test-key', ['organization-admin']),
            objectProjectId: 123,
            objectOrganizationId: 'org-1'
        );

        $this->addToAssertionCount(1);
    }

    public function testAuthorizeExistingObjectActionDelegatesToEvaluatorForUpdateAction(): void
    {
        $rule = new Rule([Role::ProjectAdmin], [Scope::Project]);
        $policy = new Policy(update: [$rule]);

        $this->aclParser->expects($this->once())
            ->method('parse')
            ->willReturn($policy);

        $capturedRequest = null;
        $this->evaluator->expects($this->once())
            ->method('evaluate')
            ->with($this->callback(function (AuthorizationRequest $request) use (&$capturedRequest) {
                $capturedRequest = $request;
                return true;
            }));

        $this->service->authorizeExistingObjectAction(
            action: 'update',
            objectType: 'notes',
            schemaData: [],
            user: new ApiKeyUser('test-key', ['project-admin']),
            objectProjectId: 123,
            objectOrganizationId: 'org-1'
        );

        $this->assertSame(Action::Update, $capturedRequest->action);
    }

    public function testAuthorizeExistingObjectActionDelegatesToEvaluatorForDeleteAction(): void
    {
        $rule = new Rule([Role::OrganizationAdmin], [Scope::Organization]);
        $policy = new Policy(delete: [$rule]);

        $this->aclParser->expects($this->once())
            ->method('parse')
            ->willReturn($policy);

        $capturedRequest = null;
        $this->evaluator->expects($this->once())
            ->method('evaluate')
            ->with($this->callback(function (AuthorizationRequest $request) use (&$capturedRequest) {
                $capturedRequest = $request;
                return true;
            }));

        $this->service->authorizeExistingObjectAction(
            action: 'delete',
            objectType: 'notes',
            schemaData: [],
            user: new ApiKeyUser('test-key', ['organization-admin']),
            objectProjectId: null,
            objectOrganizationId: 'org-1'
        );

        $this->assertSame(Action::Delete, $capturedRequest->action);
    }

    public function testAuthorizeExistingObjectActionConstructsCorrectObjectContextWhenProjectIdNull(): void
    {
        $rule = new Rule([Role::OrganizationAdmin], [Scope::Organization]);
        $policy = new Policy(delete: [$rule]);

        $this->aclParser->expects($this->once())
            ->method('parse')
            ->willReturn($policy);

        $capturedRequest = null;
        $this->evaluator->expects($this->once())
            ->method('evaluate')
            ->with($this->callback(function (AuthorizationRequest $request) use (&$capturedRequest) {
                $capturedRequest = $request;
                return true;
            }));

        $this->service->authorizeExistingObjectAction(
            action: 'delete',
            objectType: 'notes',
            schemaData: [],
            user: new ApiKeyUser('test-key', ['organization-admin']),
            objectProjectId: null,
            objectOrganizationId: 'org-1'
        );

        $this->assertSame('', $capturedRequest->objectContext->projectId);
        $this->assertSame('org-1', $capturedRequest->objectContext->organizationId);
        $this->assertFalse($capturedRequest->hint->isProjectScoped);
        $this->assertTrue($capturedRequest->hint->isOrgScoped);
    }

    public function testAuthorizeExistingObjectActionConstructsCorrectObjectContextWhenProjectIdExists(): void
    {
        $rule = new Rule([Role::ProjectAdmin], [Scope::Project]);
        $policy = new Policy(update: [$rule]);

        $this->aclParser->expects($this->once())
            ->method('parse')
            ->willReturn($policy);

        $capturedRequest = null;
        $this->evaluator->expects($this->once())
            ->method('evaluate')
            ->with($this->callback(function (AuthorizationRequest $request) use (&$capturedRequest) {
                $capturedRequest = $request;
                return true;
            }));

        $this->service->authorizeExistingObjectAction(
            action: 'update',
            objectType: 'notes',
            schemaData: [],
            user: new ApiKeyUser('test-key', ['project-admin']),
            objectProjectId: 456,
            objectOrganizationId: 'org-1'
        );

        $this->assertSame('456', $capturedRequest->objectContext->projectId);
        $this->assertSame('org-1', $capturedRequest->objectContext->organizationId);
        $this->assertTrue($capturedRequest->hint->isProjectScoped);
        $this->assertTrue($capturedRequest->hint->isOrgScoped);
    }
}
