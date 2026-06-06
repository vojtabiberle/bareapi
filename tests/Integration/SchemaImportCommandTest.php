<?php

declare(strict_types=1);

namespace Bareapi\Tests\Integration;

use Bareapi\Repository\SchemaRepository;
use Bareapi\Tests\RefreshDatabaseForKernelTestTrait;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SchemaImportCommandTest extends KernelTestCase
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

    public function testImportsExistingSchemaFilesIdempotently(): void
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $application = new Application($kernel);
        $command = $application->find('bareapi:schema:import');
        $tester = new CommandTester($command);

        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        $repository = self::getContainer()->get(SchemaRepository::class);
        self::assertInstanceOf(SchemaRepository::class, $repository);

        $note = $repository->getByObjectType('notes');
        $tag = $repository->getByObjectType('tags');
        $tagBinding = $repository->getByObjectType('tag_bindings');

        $this->assertSame('1.0.0', $note->getVersion());
        $this->assertSame('1.0.0', $tag->getVersion());
        $this->assertSame('1.0.0', $tagBinding->getVersion());
        $this->assertTrue($note->isDefault());
        $this->assertSame('Note', $note->getSchema()['title']);

        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $count = $connection->fetchOne('SELECT COUNT(*) FROM schemas');
        $this->assertIsNumeric($count);
        $this->assertSame(3, (int) $count);
    }

    public function testTrimsImportedSchemaVersion(): void
    {
        $schemaDir = sys_get_temp_dir() . '/bareapi-schema-import-' . bin2hex(random_bytes(6));
        $configSchemaDir = $schemaDir . '/config/schemas';
        self::assertTrue(mkdir($configSchemaDir, 0777, true));
        file_put_contents($configSchemaDir . '/widgets.json', json_encode([
            'title' => 'Widget',
            'version' => ' 1.2.3 ',
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $repository = self::getContainer()->get(SchemaRepository::class);
        self::assertInstanceOf(SchemaRepository::class, $repository);
        $command = new \Bareapi\Command\SchemaImportCommand($repository, $schemaDir);
        $tester = new CommandTester($command);

        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        $schema = $repository->getByObjectType('widgets');
        $this->assertSame('1.2.3', $schema->getVersion());
    }
}
