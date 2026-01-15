<?php

declare(strict_types=1);

namespace Bareapi\Command;

use Bareapi\Entity\Schema;
use Bareapi\Repository\SchemaRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'metastore:import-schemas',
    description: 'Import file-based schemas from config/schemas/ to the database',
)]
class ImportSchemasCommand extends Command
{
    public function __construct(
        private SchemaRepositoryInterface $schemaRepository,
        private string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'directory',
                'd',
                InputOption::VALUE_REQUIRED,
                'Directory containing JSON schema files',
                $this->projectDir . '/config/schemas'
            )
            ->addOption(
                'schema-version',
                null,
                InputOption::VALUE_REQUIRED,
                'Version to assign to imported schemas',
                '1.0.0'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force import even if schema already exists'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would be imported without making changes'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $directory */
        $directory = $input->getOption('directory');
        /** @var string $version */
        $version = $input->getOption('schema-version');
        $force = (bool) $input->getOption('force');
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('Importing Schemas from File System');

        if (! is_dir($directory)) {
            $io->error("Directory not found: {$directory}");

            return Command::FAILURE;
        }

        $files = glob($directory . '/*.json');
        if ($files === false || $files === []) {
            $io->warning("No JSON schema files found in {$directory}");

            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d schema file(s) to import', count($files)));

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($files as $file) {
            $objectType = basename($file, '.json');
            $io->section("Processing: {$objectType}");

            try {
                $content = file_get_contents($file);
                if ($content === false) {
                    throw new \RuntimeException("Failed to read file: {$file}");
                }

                $schemaData = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($schemaData)) {
                    throw new \RuntimeException("Invalid JSON structure in: {$file}");
                }

                // Check if schema already exists
                $existing = $this->schemaRepository->findByVersion($objectType, $version);

                if ($existing !== null && ! $force) {
                    $io->note("Schema already exists for {$objectType} version {$version}. Use --force to overwrite.");
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $io->info("[DRY RUN] Would import {$objectType} version {$version}");
                    $imported++;
                    continue;
                }

                /** @var array<string, mixed> $schemaData */
                if ($existing !== null) {
                    $existing->setSchema($schemaData);
                    $existing->setUpdatedAt(new \DateTimeImmutable());
                    $existing->setIsDefault(true);
                    $this->schemaRepository->save($existing);
                    $io->success("Updated existing schema: {$objectType}");
                } else {
                    // Clear default flag for any existing schemas of this type
                    $this->schemaRepository->clearDefaultForObjectType($objectType);

                    $schema = new Schema($objectType, $version, $schemaData);
                    $schema->setIsDefault(true);
                    $schema->setDescription($this->extractDescription($schemaData));
                    $this->schemaRepository->save($schema);
                    $io->success("Imported new schema: {$objectType}");
                }

                $imported++;
            } catch (\JsonException $e) {
                $io->error("Invalid JSON in {$file}: " . $e->getMessage());
                $errors++;
            } catch (\Exception $e) {
                $io->error("Error processing {$file}: " . $e->getMessage());
                $errors++;
            }
        }

        $io->newLine();
        $io->title('Import Summary');
        $io->table(
            ['Status', 'Count'],
            [
                ['Imported', $imported],
                ['Skipped', $skipped],
                ['Errors', $errors],
            ]
        );

        if ($dryRun) {
            $io->note('This was a dry run. No changes were made.');
        }

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $schemaData
     */
    private function extractDescription(array $schemaData): ?string
    {
        if (isset($schemaData['description']) && is_string($schemaData['description'])) {
            return $schemaData['description'];
        }

        if (isset($schemaData['title']) && is_string($schemaData['title'])) {
            return $schemaData['title'];
        }

        return null;
    }
}
