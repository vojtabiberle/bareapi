<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\Validation;

use Bareapi\Authorization\Role;
use Bareapi\Authorization\Scope;
use Bareapi\Validation\AclParser;
use PHPUnit\Framework\TestCase;

final class AclParserTest extends TestCase
{
    private AclParser $parser;

    protected function setUp(): void
    {
        $this->parser = new AclParser();
    }

    public function testReturnsEmptyPolicyWhenNoAcl(): void
    {
        $schemaData = [
            'type' => 'object',
            'properties' => [],
        ];

        $policy = $this->parser->parse($schemaData);

        $this->assertTrue($policy->isEmpty());
    }

    public function testParsesAclFromExtensionKey(): void
    {
        $schemaData = [
            'x-metastore.acl' => [
                'create' => [
                    [
                        'roles' => ['organization-admin'],
                        'scopes' => ['*'],
                    ],
                ],
            ],
        ];

        $policy = $this->parser->parse($schemaData);

        $this->assertFalse($policy->isEmpty());
        $this->assertCount(1, $policy->create);
        $this->assertContains(Role::OrganizationAdmin, $policy->create[0]->roles);
        $this->assertContains(Scope::Any, $policy->create[0]->scopes);
    }

    public function testParsesAclFromNestedKey(): void
    {
        $schemaData = [
            'x-metastore' => [
                'acl' => [
                    'update' => [
                        [
                            'role' => 'project-admin',
                            'scope' => 'project',
                        ],
                    ],
                ],
            ],
        ];

        $policy = $this->parser->parse($schemaData);

        $this->assertFalse($policy->isEmpty());
        $this->assertCount(1, $policy->update);
        $this->assertContains(Role::ProjectAdmin, $policy->update[0]->roles);
        $this->assertContains(Scope::Project, $policy->update[0]->scopes);
    }

    public function testParsesMultipleRoles(): void
    {
        $schemaData = [
            'x-metastore.acl' => [
                'delete' => [
                    [
                        'roles' => ['organization-admin', 'project-admin'],
                        'scopes' => ['organization'],
                    ],
                ],
            ],
        ];

        $policy = $this->parser->parse($schemaData);

        $this->assertCount(2, $policy->delete[0]->roles);
        $this->assertContains(Role::OrganizationAdmin, $policy->delete[0]->roles);
        $this->assertContains(Role::ProjectAdmin, $policy->delete[0]->roles);
    }

    public function testParsesMultipleScopes(): void
    {
        $schemaData = [
            'x-metastore.acl' => [
                'create' => [
                    [
                        'roles' => ['organization-admin'],
                        'scopes' => ['organization', 'project'],
                    ],
                ],
            ],
        ];

        $policy = $this->parser->parse($schemaData);

        $this->assertCount(2, $policy->create[0]->scopes);
        $this->assertContains(Scope::Organization, $policy->create[0]->scopes);
        $this->assertContains(Scope::Project, $policy->create[0]->scopes);
    }

    public function testParsesWhenCondition(): void
    {
        $schemaData = [
            'x-metastore.acl' => [
                'update' => [
                    [
                        'roles' => ['project-admin'],
                        'scopes' => ['project'],
                        'when' => "object.ProjectID != ''",
                    ],
                ],
            ],
        ];

        $policy = $this->parser->parse($schemaData);

        $this->assertSame("object.ProjectID != ''", $policy->update[0]->when);
    }

    public function testIgnoresInvalidRoles(): void
    {
        $schemaData = [
            'x-metastore.acl' => [
                'create' => [
                    [
                        'roles' => ['invalid-role', 'organization-admin'],
                        'scopes' => ['*'],
                    ],
                ],
            ],
        ];

        $policy = $this->parser->parse($schemaData);

        $this->assertCount(1, $policy->create[0]->roles);
        $this->assertContains(Role::OrganizationAdmin, $policy->create[0]->roles);
    }

    public function testIgnoresInvalidScopes(): void
    {
        $schemaData = [
            'x-metastore.acl' => [
                'create' => [
                    [
                        'roles' => ['organization-admin'],
                        'scopes' => ['invalid-scope', 'project'],
                    ],
                ],
            ],
        ];

        $policy = $this->parser->parse($schemaData);

        $this->assertCount(1, $policy->create[0]->scopes);
        $this->assertContains(Scope::Project, $policy->create[0]->scopes);
    }

    public function testCombinesSingularAndPluralKeys(): void
    {
        $schemaData = [
            'x-metastore.acl' => [
                'create' => [
                    [
                        'role' => 'organization-admin',
                        'roles' => ['project-admin'],
                        'scope' => 'organization',
                        'scopes' => ['project'],
                    ],
                ],
            ],
        ];

        $policy = $this->parser->parse($schemaData);

        $this->assertCount(2, $policy->create[0]->roles);
        $this->assertCount(2, $policy->create[0]->scopes);
    }

    public function testParsesAllActions(): void
    {
        $schemaData = [
            'x-metastore.acl' => [
                'create' => [[
                    'roles' => ['organization-admin'],
                    'scopes' => ['*'],
                ]],
                'update' => [[
                    'roles' => ['project-admin'],
                    'scopes' => ['project'],
                ]],
                'delete' => [[
                    'roles' => ['organization-admin'],
                    'scopes' => ['organization'],
                ]],
            ],
        ];

        $policy = $this->parser->parse($schemaData);

        $this->assertCount(1, $policy->create);
        $this->assertCount(1, $policy->update);
        $this->assertCount(1, $policy->delete);
    }
}
