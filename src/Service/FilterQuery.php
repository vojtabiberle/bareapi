<?php

declare(strict_types=1);

namespace Bareapi\Service;

final class FilterQuery
{
    /**
     * @param array<string, string> $filters
     */
    public function __construct(
        private array $filters,
        private ?string $orderBy,
        private string $orderDirection,
        private ?int $limit,
        private int $offset,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function filters(): array
    {
        return $this->filters;
    }

    public function orderBy(): ?string
    {
        return $this->orderBy;
    }

    public function orderDirection(): string
    {
        return $this->orderDirection;
    }

    public function limit(): ?int
    {
        return $this->limit;
    }

    public function offset(): int
    {
        return $this->offset;
    }
}
