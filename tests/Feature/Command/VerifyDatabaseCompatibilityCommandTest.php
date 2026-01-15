<?php

declare(strict_types=1);

namespace Bareapi\Tests\Feature\Command;

use Bareapi\Tests\Feature\FeatureTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class VerifyDatabaseCompatibilityCommandTest extends FeatureTestCase
{
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();

        $kernel = self::$kernel;
        $application = new Application($kernel);

        $command = $application->find('metastore:verify-db');
        $this->commandTester = new CommandTester($command);
    }

    public function testVerifyPassesWithValidDatabase(): void
    {
        $this->commandTester->execute([]);

        // The database should be valid since we're using the test fixtures
        $statusCode = $this->commandTester->getStatusCode();
        $output = $this->commandTester->getDisplay();

        // Either success (0) or success with warnings (0)
        $this->assertContains($statusCode, [0], "Expected success, got status {$statusCode}. Output: {$output}");
    }

    public function testVerifyChecksRequiredTables(): void
    {
        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();

        // Should check for required tables
        $this->assertStringContainsString('meta_objects', $output);
        $this->assertStringContainsString('meta_object_revisions', $output);
        $this->assertStringContainsString('schemas', $output);
    }

    public function testVerifyChecksTableColumns(): void
    {
        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();

        // Should check for columns
        $this->assertStringContainsString('Checking Table Columns', $output);
    }

    public function testVerifyChecksIndexes(): void
    {
        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();

        // Should check indexes
        $this->assertStringContainsString('Checking Indexes', $output);
    }

    public function testVerifyChecksPostgresVersion(): void
    {
        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();

        // Should show PostgreSQL version
        $this->assertStringContainsString('PostgreSQL', $output);
    }

    public function testVerifyShowsConnectionSuccess(): void
    {
        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Database connection successful', $output);
    }

    public function testVerifyShowsSummary(): void
    {
        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();

        $this->assertStringContainsString('Verification Summary', $output);
    }
}
