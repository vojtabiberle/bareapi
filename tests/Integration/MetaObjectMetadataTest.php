<?php

declare(strict_types=1);

namespace Bareapi\Tests\Integration;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

final class MetaObjectMetadataTest extends WebTestCase
{
    protected function setUp(): void
    {
        $this->migrateDatabase();
    }

    public function testRepositoryCreateStoresDocumentMetadataSeparately(): void
    {
        $client = self::createClient();
        $client->request(
            'POST',
            '/api/v1/repository/notes',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'name' => 'Metadata Test',
                'branch' => 'draft',
                'schemaVersion' => '1.0.0',
                'data' => [
                    'title' => 'Metadata title',
                    'content' => 'Metadata content',
                ],
            ], JSON_THROW_ON_ERROR)
        );
        $this->assertResponseStatusCodeSame(201);

        $content = $client->getResponse()->getContent();
        $response = json_decode(is_string($content) ? $content : '', true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('data', $response);
        $this->assertIsArray($response['data']);
        $this->assertArrayHasKey('id', $response['data']);
        $this->assertIsString($response['data']['id']);

        $connection = self::getContainer()->get(Connection::class);
        $this->assertInstanceOf(Connection::class, $connection);
        $row = $connection->fetchAssociative(
            'SELECT object_type, branch, name, schema_version, last_updated, deleted_at FROM meta_objects WHERE id = :id',
            [
                'id' => $response['data']['id'],
            ],
        );
        $this->assertIsArray($row);
        $this->assertSame('notes', $row['object_type']);
        $this->assertSame('draft', $row['branch']);
        $this->assertSame('Metadata Test', $row['name']);
        $this->assertSame('1.0.0', $row['schema_version']);
        $this->assertNotNull($row['last_updated']);
        $this->assertNull($row['deleted_at']);
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
}
