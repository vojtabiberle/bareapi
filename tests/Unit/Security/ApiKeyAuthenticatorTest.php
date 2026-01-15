<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\Security;

use Bareapi\Security\ApiKeyAuthenticator;
use Bareapi\Security\ApiKeyUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class ApiKeyAuthenticatorTest extends TestCase
{
    private const VALID_API_KEY = 'valid-test-api-key';

    private ApiKeyAuthenticator $authenticator;

    protected function setUp(): void
    {
        $this->authenticator = new ApiKeyAuthenticator(self::VALID_API_KEY);
    }

    public function testSupportsReturnsTrueWhenApiKeyHeaderPresent(): void
    {
        $request = new Request();
        $request->headers->set('X-API-Key', 'some-key');

        $result = $this->authenticator->supports($request);

        $this->assertTrue($result);
    }

    public function testSupportsReturnsFalseWhenApiKeyHeaderMissing(): void
    {
        $request = new Request();

        $result = $this->authenticator->supports($request);

        $this->assertFalse($result);
    }

    public function testAuthenticateThrowsExceptionWhenApiKeyEmpty(): void
    {
        $request = new Request();
        $request->headers->set('X-API-Key', '');

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('No API key provided');

        $this->authenticator->authenticate($request);
    }

    public function testAuthenticateThrowsExceptionWhenApiKeyInvalid(): void
    {
        $request = new Request();
        $request->headers->set('X-API-Key', 'wrong-api-key');

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Invalid API key');

        $this->authenticator->authenticate($request);
    }

    public function testAuthenticateReturnsSelfValidatingPassportWhenKeyValid(): void
    {
        $request = new Request();
        $request->headers->set('X-API-Key', self::VALID_API_KEY);

        $result = $this->authenticator->authenticate($request);

        $this->assertInstanceOf(SelfValidatingPassport::class, $result);
    }

    public function testAuthenticateUserBadgeLoaderCreatesApiKeyUserCorrectly(): void
    {
        $request = new Request();
        $request->headers->set('X-API-Key', self::VALID_API_KEY);

        $passport = $this->authenticator->authenticate($request);
        $user = $passport->getUser();

        $this->assertInstanceOf(ApiKeyUser::class, $user);
        $this->assertSame(self::VALID_API_KEY, $user->getUserIdentifier());
    }

    public function testOnAuthenticationSuccessReturnsNull(): void
    {
        $request = new Request();
        $token = $this->createMock(TokenInterface::class);

        $result = $this->authenticator->onAuthenticationSuccess($request, $token, 'main');

        $this->assertNull($result);
    }

    public function testOnAuthenticationFailureReturnsJsonResponseWith401Status(): void
    {
        $request = new Request();
        $exception = new CustomUserMessageAuthenticationException('Test error');

        $result = $this->authenticator->onAuthenticationFailure($request, $exception);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertSame(401, $result->getStatusCode());
    }

    public function testOnAuthenticationFailureResponseContainsCorrectErrorStructure(): void
    {
        $request = new Request();
        $exception = new CustomUserMessageAuthenticationException('Invalid API key');

        $result = $this->authenticator->onAuthenticationFailure($request, $exception);

        $data = json_decode($result->getContent(), true);
        $this->assertSame(401, $data['error']);
        $this->assertSame('401', $data['code']);
        $this->assertSame('Invalid API key', $data['message']);
        $this->assertSame('error', $data['status']);
    }
}
