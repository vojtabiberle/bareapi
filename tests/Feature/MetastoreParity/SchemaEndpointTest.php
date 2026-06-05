<?php

declare(strict_types=1);

namespace Bareapi\Tests\Feature\MetastoreParity;

use Bareapi\Repository\SchemaRepository;
use Bareapi\Tests\Feature\FeatureTestCase;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

final class SchemaEndpointTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        $this->migrateDatabase();
        parent::setUp();

        $connection = self::getContainer()->get(Connection::class);
        $this->assertInstanceOf(Connection::class, $connection);
        $connection->executeStatement('TRUNCATE TABLE schemas RESTART IDENTITY CASCADE');
    }

    public function testSchemaEndpointReturnsDefaultAndSpecificVersion(): void
    {
        $repository = self::getContainer()->get(SchemaRepository::class);
        $this->assertInstanceOf(SchemaRepository::class, $repository);

        $repository->save('note', '1.0.0', false, [
            'title' => 'note',
            'version' => '1.0.0',
            'type' => 'object',
        ]);
        $repository->save('note', '1.1.0', true, [
            'title' => 'note',
            'version' => '1.1.0',
            'type' => 'object',
        ]);

        $default = $this->requestSchema('/api/v1/schema/note');
        $versioned = $this->requestSchema('/api/v1/schema/note/1.0.0');

        $this->assertSame('1.1.0', $default['version']);
        $this->assertSame('1.0.0', $versioned['version']);
        $this->assertSame('note', $default['title']);
        $this->assertIsString($default['$id']);
        $this->assertStringEndsWith('/api/v1/schema/note/1.1.0', $default['$id']);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestSchema(string $path): array
    {
        $this->client->request('GET', $path);
        $this->assertResponseStatusCodeSame(200);

        $content = $this->client->getResponse()->getContent();
        $response = json_decode(is_string($content) ? $content : '', true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);

        return $this->stringKeyedArray($response);
    }

    private function migrateDatabase(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $application->setAutoExit(false);
        $application->run(new ArrayInput([
            'command' => 'doctrine:migrations:migrate',
            '--env' => 'test',
            '--no-interaction' => true,
        ]), new NullOutput());
        self::ensureKernelShutdown();
    }

    /**
     * @param array<mixed> $array
     * @return array<string, mixed>
     */
    private function stringKeyedArray(array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
