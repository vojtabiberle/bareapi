<?php

declare(strict_types=1);

namespace Bareapi\Tests\Feature\Command;

use Bareapi\Entity\Schema;
use Bareapi\Tests\Feature\FeatureTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class ImportSchemasCommandTest extends FeatureTestCase
{
    private CommandTester $commandTester;

    private string $tempDir;

    private Filesystem $filesystem;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        $kernel = self::$kernel;
        $application = new Application($kernel);

        $command = $application->find('metastore:import-schemas');
        $this->commandTester = new CommandTester($command);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);
        $this->em = $em;
        $this->filesystem = new Filesystem();

        // Create temp directory for test schemas
        $this->tempDir = sys_get_temp_dir() . '/metastore_test_schemas_' . uniqid();
        $this->filesystem->mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (isset($this->tempDir) && is_dir($this->tempDir)) {
            $this->filesystem->remove($this->tempDir);
        }
        parent::tearDown();
    }

    public function testImportSchemaFromFile(): void
    {
        $this->createSchemaFile('notes', [
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type' => 'string',
                ],
            ],
            'required' => ['title'],
            'description' => 'Notes schema',
        ]);

        $this->commandTester->execute([
            '--directory' => $this->tempDir,
            '--schema-version' => '1.0.0',
        ]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Imported new schema: notes', $this->commandTester->getDisplay());

        // Verify schema was created in DB
        $schema = $this->em->getRepository(Schema::class)->findOneBy([
            'objectType' => 'notes',
        ]);
        $this->assertNotNull($schema);
        $this->assertSame('Notes schema', $schema->getDescription());
    }

    public function testImportMultipleSchemas(): void
    {
        $this->createSchemaFile('notes', [
            'type' => 'object',
            'properties' => [],
        ]);
        $this->createSchemaFile('articles', [
            'type' => 'object',
            'properties' => [],
        ]);

        $this->commandTester->execute([
            '--directory' => $this->tempDir,
        ]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Imported', $output);
        $this->assertStringContainsString('2', $output); // 2 imported
    }

    public function testImportWithCustomVersion(): void
    {
        $this->createSchemaFile('versioned', [
            'type' => 'object',
            'properties' => [],
        ]);

        $this->commandTester->execute([
            '--directory' => $this->tempDir,
            '--schema-version' => '2.5.0',
        ]);

        $this->assertSame(0, $this->commandTester->getStatusCode());

        $schema = $this->em->getRepository(Schema::class)->findOneBy([
            'objectType' => 'versioned',
        ]);
        $this->assertNotNull($schema);
        $this->assertSame('2.5.0', $schema->getVersion());
    }

    public function testImportWithDryRun(): void
    {
        $this->createSchemaFile('dry-run-test', [
            'type' => 'object',
            'properties' => [],
        ]);

        $this->commandTester->execute([
            '--directory' => $this->tempDir,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('[DRY RUN]', $this->commandTester->getDisplay());
        $this->assertStringContainsString('This was a dry run. No changes were made.', $this->commandTester->getDisplay());

        // Verify nothing was created
        $schema = $this->em->getRepository(Schema::class)->findOneBy([
            'objectType' => 'dry-run-test',
        ]);
        $this->assertNull($schema);
    }

    public function testImportSkipsExistingSchemas(): void
    {
        // Create existing schema
        $existingSchema = new Schema('existing', '1.0.0', [
            'type' => 'object',
        ]);
        $existingSchema->setIsDefault(true);
        $this->em->persist($existingSchema);
        $this->em->flush();

        $this->createSchemaFile('existing', [
            'type' => 'object',
            'properties' => [
                'new' => [],
            ],
        ]);

        $this->commandTester->execute([
            '--directory' => $this->tempDir,
        ]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Schema already exists', $this->commandTester->getDisplay());
        $this->assertStringContainsString('Skipped', $this->commandTester->getDisplay());
    }

    public function testImportOverwritesWithForce(): void
    {
        // Create existing schema
        $existingSchema = new Schema('force-test', '1.0.0', [
            'type' => 'object',
            'old' => true,
        ]);
        $existingSchema->setIsDefault(true);
        $this->em->persist($existingSchema);
        $this->em->flush();

        $this->createSchemaFile('force-test', [
            'type' => 'object',
            'properties' => [
                'new' => [
                    'type' => 'string',
                ],
            ],
        ]);

        $this->commandTester->execute([
            '--directory' => $this->tempDir,
            '--force' => true,
        ]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Updated existing schema', $this->commandTester->getDisplay());
    }

    public function testImportFailsForNonExistentDirectory(): void
    {
        $this->commandTester->execute([
            '--directory' => '/nonexistent/directory/path',
        ]);

        $this->assertSame(1, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Directory not found', $this->commandTester->getDisplay());
    }

    public function testImportHandlesInvalidJson(): void
    {
        file_put_contents($this->tempDir . '/invalid.json', 'not valid json{{{');

        $this->commandTester->execute([
            '--directory' => $this->tempDir,
        ]);

        $this->assertSame(1, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Invalid JSON', $this->commandTester->getDisplay());
        $this->assertStringContainsString('Errors', $this->commandTester->getDisplay());
    }

    public function testImportHandlesEmptyDirectory(): void
    {
        $this->commandTester->execute([
            '--directory' => $this->tempDir,
        ]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('No JSON schema files found', $this->commandTester->getDisplay());
    }

    public function testImportSetsDefaultFlag(): void
    {
        $this->createSchemaFile('default-test', [
            'type' => 'object',
            'properties' => [],
        ]);

        $this->commandTester->execute([
            '--directory' => $this->tempDir,
        ]);

        $schema = $this->em->getRepository(Schema::class)->findOneBy([
            'objectType' => 'default-test',
        ]);
        $this->assertTrue($schema->isDefault());
    }

    public function testImportExtractsDescriptionFromTitle(): void
    {
        $this->createSchemaFile('title-desc', [
            'type' => 'object',
            'title' => 'My Title Description',
            'properties' => [],
        ]);

        $this->commandTester->execute([
            '--directory' => $this->tempDir,
        ]);

        $schema = $this->em->getRepository(Schema::class)->findOneBy([
            'objectType' => 'title-desc',
        ]);
        $this->assertSame('My Title Description', $schema->getDescription());
    }

    /**
     * @param array<string, mixed> $schemaData
     */
    private function createSchemaFile(string $objectType, array $schemaData): void
    {
        $encoded = json_encode($schemaData, JSON_PRETTY_PRINT);
        if ($encoded !== false) {
            file_put_contents(
                $this->tempDir . '/' . $objectType . '.json',
                $encoded
            );
        }
    }
}
