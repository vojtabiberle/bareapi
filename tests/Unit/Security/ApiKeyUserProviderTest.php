<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\Security;

use Bareapi\Security\ApiKeyUser;
use Bareapi\Security\ApiKeyUserProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;

final class ApiKeyUserProviderTest extends TestCase
{
    private ApiKeyUserProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new ApiKeyUserProvider();
    }

    public function testRefreshUserReturnsSameApiKeyUserInstance(): void
    {
        $user = new ApiKeyUser('test-key', ['organization-admin']);

        $result = $this->provider->refreshUser($user);

        $this->assertSame($user, $result);
    }

    public function testRefreshUserThrowsExceptionForNonApiKeyUser(): void
    {
        $user = $this->createMock(UserInterface::class);

        $this->expectException(UnsupportedUserException::class);
        $this->expectExceptionMessage('Invalid user class');

        $this->provider->refreshUser($user);
    }

    public function testSupportsClassReturnsTrueForApiKeyUserClass(): void
    {
        $result = $this->provider->supportsClass(ApiKeyUser::class);

        $this->assertTrue($result);
    }

    public function testSupportsClassReturnsFalseForOtherClasses(): void
    {
        $result = $this->provider->supportsClass(\stdClass::class);

        $this->assertFalse($result);
    }

    public function testSupportsClassReturnsFalseForUserInterface(): void
    {
        $result = $this->provider->supportsClass(UserInterface::class);

        $this->assertFalse($result);
    }

    public function testLoadUserByIdentifierReturnsApiKeyUserWithIdentifier(): void
    {
        $result = $this->provider->loadUserByIdentifier('my-api-key');

        $this->assertInstanceOf(ApiKeyUser::class, $result);
        $this->assertSame('my-api-key', $result->getUserIdentifier());
    }

    public function testLoadUserByIdentifierHandlesEmptyIdentifierByUsingUnknown(): void
    {
        $result = $this->provider->loadUserByIdentifier('');

        $this->assertInstanceOf(ApiKeyUser::class, $result);
        $this->assertSame('unknown', $result->getUserIdentifier());
    }
}
