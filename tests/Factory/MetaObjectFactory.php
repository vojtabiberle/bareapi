<?php

declare(strict_types=1);

namespace Bareapi\Tests\Factory;

use Bareapi\Entity\MetaObject;

final class MetaObjectFactory
{
    /**
     * Create a valid MetaObject for testing.
     *
     * @param array<string, mixed> $data
     */
    public static function create(
        array $data = [
            'title' => 'Test',
            'content' => 'Sample',
        ],
        string $type = 'notes',
        string $schemaVersion = '1.0'
    ): MetaObject {
        return new MetaObject($type, $schemaVersion, $data);
    }
}
