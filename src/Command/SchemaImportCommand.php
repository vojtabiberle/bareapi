<?php

declare(strict_types=1);

namespace Bareapi\Command;

use Bareapi\Controller\ControllerUtil;
use Bareapi\Repository\SchemaRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

#[AsCommand(name: 'bareapi:schema:import', description: 'Import JSON schema files into the schema store')]
final class SchemaImportCommand extends Command
{
    public function __construct(
        private SchemaRepository $repository,
        private string $projectDir
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $schemaDir = $this->projectDir . '/config/schemas';
        $finder = new Finder();
        $finder->files()->in($schemaDir)->name('*.json')->sortByName();

        $imported = 0;
        foreach ($finder as $file) {
            $schema = json_decode($file->getContents(), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($schema)) {
                continue;
            }

            $schemaData = ControllerUtil::toStringKeyedArray($schema);
            $objectType = $this->objectType($schemaData, $file->getBasename('.json'));
            $version = $this->version($schemaData);
            $description = isset($schemaData['description']) && is_string($schemaData['description'])
                ? $schemaData['description']
                : null;

            $this->repository->save(
                $objectType,
                $version,
                true,
                $schemaData,
                $description,
                [
                    'source' => 'schema-import-command',
                    'file' => $file->getRelativePathname(),
                ],
            );
            $imported++;
        }

        $output->writeln(sprintf('Imported %d schema file(s).', $imported));

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function objectType(array $schema, string $fallback): string
    {
        return isset($schema['title']) && is_string($schema['title']) && trim($schema['title']) !== ''
            ? $schema['title']
            : $fallback;
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function version(array $schema): string
    {
        return isset($schema['version']) && is_string($schema['version']) && trim($schema['version']) !== ''
            ? $schema['version']
            : '1.0.0';
    }
}
