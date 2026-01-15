<?php

declare(strict_types=1);

namespace Bareapi\Service;

use Bareapi\Entity\MetaObject;

/**
 * Interface for maintaining the meta_refs reverse index.
 */
interface ReferenceIndexServiceInterface
{
    /**
     * Sync refs after a successful write operation.
     *
     * @param array<string, mixed> $data Current payload data
     */
    public function syncRefsForObject(MetaObject $metaObject, array $data): void;

    /**
     * Remove all refs when an object is deleted.
     */
    public function removeRefsForObject(MetaObject $metaObject): void;
}
