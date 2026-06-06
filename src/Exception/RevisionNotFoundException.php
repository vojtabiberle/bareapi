<?php

declare(strict_types=1);

namespace Bareapi\Exception;

final class RevisionNotFoundException extends \RuntimeException
{
    public function __construct(string $uuid, int $revision)
    {
        parent::__construct(sprintf('Revision %d for object "%s" not found.', $revision, $uuid));
    }
}
