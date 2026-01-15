<?php

declare(strict_types=1);

namespace Bareapi\Authorization;

class Policy
{
    /**
     * @param Rule[] $create
     * @param Rule[] $update
     * @param Rule[] $delete
     */
    public function __construct(
        public readonly array $create = [],
        public readonly array $update = [],
        public readonly array $delete = [],
    ) {
    }

    /**
     * @return Rule[]
     */
    public function getRulesFor(Action $action): array
    {
        return match ($action) {
            Action::Create => $this->create,
            Action::Update => $this->update,
            Action::Delete => $this->delete,
            default => [],
        };
    }

    public function validate(): void
    {
        foreach ([Action::Create, Action::Update, Action::Delete] as $action) {
            $rules = $this->getRulesFor($action);
            if ($rules === []) {
                continue;
            }
            foreach ($rules as $i => $rule) {
                $rule->validate($action, $i);
            }
        }
    }

    public function isEmpty(): bool
    {
        return $this->create === [] && $this->update === [] && $this->delete === [];
    }
}
