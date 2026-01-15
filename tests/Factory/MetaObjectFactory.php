<?php

declare(strict_types=1);

namespace Bareapi\Tests\Factory;

use Bareapi\Entity\MetaObject;
use Bareapi\Entity\MetaObjectRevision;

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
        string $objectType = 'notes',
        string $schemaVersion = '1.0',
        string $name = 'test-object',
        string $organizationId = 'org-123',
        ?int $projectId = 123,
        string $branch = 'main',
    ): MetaObject {
        $metaObject = new MetaObject($objectType, $schemaVersion, $name, $organizationId);
        $metaObject->setProjectId($projectId);
        $metaObject->setBranch($branch);

        $revision = new MetaObjectRevision($metaObject, 1, $data);
        $metaObject->addRevision($revision);

        return $metaObject;
    }
}
