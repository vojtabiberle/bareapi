<?php

declare(strict_types=1);

namespace Bareapi\Repository;

use Bareapi\Entity\MetaObject;

final class MetaObjectListItem
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private MetaObject $object,
        private array $data,
        private int $revision,
        private \DateTimeImmutable $revisionCreatedAt,
    ) {
    }

    public function object(): MetaObject
    {
        return $this->object;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    public function revision(): int
    {
        return $this->revision;
    }

    public function revisionCreatedAt(): \DateTimeImmutable
    {
        return $this->revisionCreatedAt;
    }
}
