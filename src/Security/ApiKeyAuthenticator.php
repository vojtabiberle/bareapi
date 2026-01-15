<?php

declare(strict_types=1);

namespace Bareapi\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApiKeyAuthenticator extends AbstractAuthenticator
{
    private const API_KEY_HEADER = 'X-API-Key';

    public function __construct(
        private string $apiKey,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has(self::API_KEY_HEADER);
    }

    public function authenticate(Request $request): Passport
    {
        $apiKey = $request->headers->get(self::API_KEY_HEADER);

        if ($apiKey === null || $apiKey === '') {
            throw new CustomUserMessageAuthenticationException('No API key provided');
        }

        if ($apiKey !== $this->apiKey) {
            throw new CustomUserMessageAuthenticationException('Invalid API key');
        }

        return new SelfValidatingPassport(
            new UserBadge($apiKey, fn (string $userIdentifier) => new ApiKeyUser($userIdentifier !== '' ? $userIdentifier : 'unknown'))
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $data = [
            'error' => 401,
            'code' => '401',
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData()),
            'status' => 'error',
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }
}
