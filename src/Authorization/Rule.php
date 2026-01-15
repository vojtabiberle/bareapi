<?php

declare(strict_types=1);

namespace Bareapi\Authorization;

class Rule
{
    /**
     * @param Role[] $roles
     * @param Scope[] $scopes
     */
    public function __construct(
        public readonly array $roles,
        public readonly array $scopes,
        public readonly ?string $when = null,
    ) {
    }

    public function validate(Action $action, int $index): void
    {
        if ($this->roles === []) {
            throw new \InvalidArgumentException(
                sprintf('Rule %d for action "%s": at least one role must be defined', $index, $action->value)
            );
        }

        if ($this->scopes === []) {
            throw new \InvalidArgumentException(
                sprintf('Rule %d for action "%s": at least one scope must be defined', $index, $action->value)
            );
        }
    }
}
