<?php

declare(strict_types=1);

namespace Bareapi\Security;

use Symfony\Component\Security\Core\User\UserInterface;

class ApiKeyUser implements UserInterface
{
    /**
     * @param non-empty-string $apiKey
     * @param string[] $roles
     */
    public function __construct(
        private string $apiKey,
        private array $roles = ['ROLE_API_USER'],
    ) {
    }

    /**
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        return $this->apiKey;
    }

    /**
     * @return string[]
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function eraseCredentials(): void
    {
    }
}
