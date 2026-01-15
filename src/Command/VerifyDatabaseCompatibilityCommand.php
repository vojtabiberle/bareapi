<?php

declare(strict_types=1);

namespace Bareapi\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'metastore:verify-db',
    description: 'Verify database structure and compatibility',
)]
class VerifyDatabaseCompatibilityCommand extends Command
{
    private const REQUIRED_TABLES = [
        'meta_objects',
        'meta_object_revisions',
        'schemas',
    ];

    private const META_OBJECTS_COLUMNS = [
        'uuid',
        'object_type',
        'schema_version',
        'branch',
        'name',
        'project_id',
        'organization_id',
        'last_updated',
        'created_at',
        'deleted_at',
    ];

    private const META_OBJECT_REVISIONS_COLUMNS = [
        'id',
        'uuid',
        'revision',
        'parent_id',
        'data',
        'created_at',
        'deleted_at',
    ];

    private const SCHEMAS_COLUMNS = [
        'id',
        'object_type',
        'version',
        'is_default',
        'schema',
        'description',
        'created_at',
        'updated_at',
        'created_by',
    ];

    public function __construct(
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Verifying Database Compatibility');

        $errors = [];
        $warnings = [];

        // Test database connection
        $io->section('Testing Database Connection');
        try {
            $this->em->getConnection()->executeQuery('SELECT 1');
            $io->success('Database connection successful');
        } catch (\Exception $e) {
            $io->error('Database connection failed: ' . $e->getMessage());

            return Command::FAILURE;
        }

        // Check PostgreSQL version
        $io->section('Checking PostgreSQL Version');
        try {
            $result = $this->em->getConnection()->executeQuery('SELECT version()')->fetchOne();
            if (is_string($result)) {
                $io->info("PostgreSQL version: {$result}");

                // Check for PostgreSQL 10+ (required for JSONB features)
                if (preg_match('/PostgreSQL (\d+)/', $result, $matches)) {
                    $majorVersion = (int) $matches[1];
                    if ($majorVersion < 10) {
                        $warnings[] = 'PostgreSQL version 10+ is recommended for optimal JSONB support';
                    }
                }
            }
        } catch (\Exception $e) {
            $warnings[] = 'Could not determine PostgreSQL version';
        }

        // Check required tables
        $io->section('Checking Required Tables');
        foreach (self::REQUIRED_TABLES as $table) {
            if ($this->tableExists($table)) {
                $io->info("✓ Table '{$table}' exists");
            } else {
                $errors[] = "Missing required table: {$table}";
                $io->error("✗ Table '{$table}' is missing");
            }
        }

        // Check table columns
        $io->section('Checking Table Columns');

        if ($this->tableExists('meta_objects')) {
            $this->checkTableColumns($io, 'meta_objects', self::META_OBJECTS_COLUMNS, $errors);
        }

        if ($this->tableExists('meta_object_revisions')) {
            $this->checkTableColumns($io, 'meta_object_revisions', self::META_OBJECT_REVISIONS_COLUMNS, $errors);
        }

        if ($this->tableExists('schemas')) {
            $this->checkTableColumns($io, 'schemas', self::SCHEMAS_COLUMNS, $errors);
        }

        // Check indexes
        $io->section('Checking Indexes');
        $this->checkIndexes($io, $warnings);

        // Summary
        $io->newLine();
        $io->title('Verification Summary');

        if ($errors !== []) {
            $io->error('Errors found:');
            foreach ($errors as $error) {
                $io->writeln("  • {$error}");
            }
        }

        if ($warnings !== []) {
            $io->warning('Warnings:');
            foreach ($warnings as $warning) {
                $io->writeln("  • {$warning}");
            }
        }

        if ($errors === [] && $warnings === []) {
            $io->success('Database structure is fully compatible!');

            return Command::SUCCESS;
        }

        if ($errors === []) {
            $io->note('Database structure is compatible with warnings.');

            return Command::SUCCESS;
        }

        $io->error('Database structure has issues. Please run migrations.');

        return Command::FAILURE;
    }

    private function tableExists(string $tableName): bool
    {
        try {
            $sql = "SELECT EXISTS (
                SELECT FROM information_schema.tables
                WHERE table_schema = 'public'
                AND table_name = :table
            )";
            $result = $this->em->getConnection()->executeQuery($sql, [
                'table' => $tableName,
            ])->fetchOne();

            return (bool) $result;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * @param string[] $expectedColumns
     * @param string[] $errors
     */
    private function checkTableColumns(SymfonyStyle $io, string $table, array $expectedColumns, array &$errors): void
    {
        try {
            $sql = "SELECT column_name FROM information_schema.columns
                    WHERE table_schema = 'public' AND table_name = :table";
            $result = $this->em->getConnection()->executeQuery($sql, [
                'table' => $table,
            ])->fetchAllAssociative();

            $existingColumns = array_column($result, 'column_name');

            foreach ($expectedColumns as $column) {
                if (in_array($column, $existingColumns, true)) {
                    $io->info("✓ Column '{$table}.{$column}' exists");
                } else {
                    $errors[] = "Missing column: {$table}.{$column}";
                    $io->error("✗ Column '{$table}.{$column}' is missing");
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Failed to check columns for {$table}: " . $e->getMessage();
        }
    }

    /**
     * @param string[] $warnings
     */
    private function checkIndexes(SymfonyStyle $io, array &$warnings): void
    {
        $expectedIndexes = [
            'idx_meta_objects_project_id',
            'idx_meta_objects_type_project',
            'idx_meta_objects_org_project_type',
            'idx_meta_object_revisions_uuid_revision',
            'idx_schemas_object_type',
            'idx_schemas_is_default',
        ];

        try {
            $sql = "SELECT indexname FROM pg_indexes WHERE schemaname = 'public'";
            $result = $this->em->getConnection()->executeQuery($sql)->fetchAllAssociative();
            $existingIndexes = array_column($result, 'indexname');

            foreach ($expectedIndexes as $index) {
                if (in_array($index, $existingIndexes, true)) {
                    $io->info("✓ Index '{$index}' exists");
                } else {
                    $warnings[] = "Missing index: {$index} (performance may be affected)";
                    $io->note("△ Index '{$index}' is missing (recommended for performance)");
                }
            }
        } catch (\Exception $e) {
            $warnings[] = 'Could not verify indexes: ' . $e->getMessage();
        }
    }
}
