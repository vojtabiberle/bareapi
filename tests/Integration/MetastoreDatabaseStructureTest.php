<?php

declare(strict_types=1);

namespace Bareapi\Tests\Integration;

use Bareapi\Tests\RefreshDatabaseForKernelTestTrait;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MetastoreDatabaseStructureTest extends KernelTestCase
{
    use RefreshDatabaseForKernelTestTrait;

    public function testRevisionTableDoesNotContainUnusedParentReference(): void
    {
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);

        $columnCount = $connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(*)
                FROM information_schema.columns
                WHERE table_name = 'meta_object_revisions'
                    AND column_name = 'parent_id'
            SQL
        );

        $this->assertIsNumeric($columnCount);
        $this->assertSame(0, (int) $columnCount);
    }
}
