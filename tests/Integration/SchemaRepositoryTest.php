<?php

declare(strict_types=1);

namespace Bareapi\Tests\Integration;

use Bareapi\Repository\SchemaRepository;
use Bareapi\Tests\RefreshDatabaseForKernelTestTrait;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SchemaRepositoryTest extends KernelTestCase
{
    use RefreshDatabaseForKernelTestTrait {
        setUp as migrateDatabase;
    }

    protected function setUp(): void
    {
        $this->migrateDatabase();

        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $connection->executeStatement('TRUNCATE TABLE schemas RESTART IDENTITY CASCADE');
    }

    public function testStoresVersionedSchemasWithOneDefaultPerObjectType(): void
    {
        $repository = self::getContainer()->get(SchemaRepository::class);
        self::assertInstanceOf(SchemaRepository::class, $repository);

        $repository->save('note', '1.0.0', true, [
            'title' => 'note',
            'version' => '1.0.0',
            'type' => 'object',
        ], 'Initial note schema');
        $repository->save('note', '1.1.0', true, [
            'title' => 'note',
            'version' => '1.1.0',
            'type' => 'object',
        ], 'Updated note schema');

        $defaultSchema = $repository->getByObjectType('note');
        $versionedSchema = $repository->getByObjectType('note', '1.0.0');

        $this->assertSame('1.1.0', $defaultSchema->getVersion());
        $this->assertTrue($defaultSchema->isDefault());
        $this->assertSame('Updated note schema', $defaultSchema->getDescription());
        $this->assertSame('1.0.0', $versionedSchema->getVersion());
        $this->assertFalse($versionedSchema->isDefault());
    }
}
