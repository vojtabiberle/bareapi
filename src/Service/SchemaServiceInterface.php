<?php

declare(strict_types=1);

namespace Bareapi\Service;

interface SchemaServiceInterface
{
    /**
     * @throws \Bareapi\Exception\SchemaNotFoundException
     * @return array<int, string>
     */
    public function getFilterableFields(string $type): array;
}
