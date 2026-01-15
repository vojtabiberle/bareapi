<?php

declare(strict_types=1);

namespace Bareapi\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<ApiKeyUser>
 */
class ApiKeyUserProvider implements UserProviderInterface
{
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (! $user instanceof ApiKeyUser) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return $class === ApiKeyUser::class || is_subclass_of($class, ApiKeyUser::class);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        return new ApiKeyUser($identifier !== '' ? $identifier : 'unknown');
    }
}
