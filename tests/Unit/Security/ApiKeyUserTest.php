<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\Security;

use Bareapi\Security\ApiKeyUser;
use PHPUnit\Framework\TestCase;

final class ApiKeyUserTest extends TestCase
{
    public function testGetUserIdentifierReturnsApiKey(): void
    {
        $user = new ApiKeyUser('test-api-key-123');

        $this->assertSame('test-api-key-123', $user->getUserIdentifier());
    }

    public function testGetRolesReturnsDefaultRoleWhenNoRolesProvided(): void
    {
        $user = new ApiKeyUser('test-key');

        $this->assertSame(['ROLE_API_USER'], $user->getRoles());
    }

    public function testGetRolesReturnsCustomRolesWhenProvided(): void
    {
        $roles = ['organization-admin', 'project-admin'];
        $user = new ApiKeyUser('test-key', $roles);

        $this->assertSame($roles, $user->getRoles());
    }

    public function testEraseCredentialsDoesNotThrow(): void
    {
        $user = new ApiKeyUser('test-key');

        $user->eraseCredentials();

        $this->addToAssertionCount(1);
    }

    public function testImplementsUserInterface(): void
    {
        $user = new ApiKeyUser('test-key');

        $this->assertInstanceOf(\Symfony\Component\Security\Core\User\UserInterface::class, $user);
    }
}
