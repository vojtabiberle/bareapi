<?php

declare(strict_types=1);

namespace Bareapi\Tests\Feature\MetastoreParity;

use Bareapi\Tests\Feature\FeatureTestCase;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

final class SoftDeleteTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        $this->migrateDatabase();
        parent::setUp();
    }

    public function testRepositoryDeleteSoftDeletesObject(): void
    {
        $created = $this->createNote('Soft deleted note');
        $id = $this->jsonApiId($created);

        $this->client->request('DELETE', '/api/v1/repository/notes/' . $id);
        $this->assertResponseStatusCodeSame(204);

        $connection = self::getContainer()->get(Connection::class);
        $this->assertInstanceOf(Connection::class, $connection);
        $deletedAt = $connection->fetchOne('SELECT deleted_at FROM meta_objects WHERE id = :id', [
            'id' => $id,
        ]);
        $this->assertNotFalse($deletedAt);
        $this->assertNotNull($deletedAt);

        $this->client->request('GET', '/api/v1/repository/notes/' . $id);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testRepositoryDeleteRevisionSoftDeletesOnlyThatRevision(): void
    {
        $created = $this->createNote('Revision soft delete');
        $id = $this->jsonApiId($created);

        $this->client->request(
            'PATCH',
            '/api/v1/repository/notes/' . $id,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'data' => [
                    'content' => 'Updated content',
                ],
            ], JSON_THROW_ON_ERROR)
        );
        $this->assertResponseStatusCodeSame(200);

        $this->client->request('DELETE', '/api/v1/repository/notes/' . $id . '/revisions/1');
        $this->assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/v1/repository/notes/' . $id . '/revisions/1');
        $this->assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/api/v1/repository/notes/' . $id);
        $this->assertResponseStatusCodeSame(200);
    }

    /**
     * @return array<string, mixed>
     */
    private function createNote(string $name): array
    {
        $this->client->request(
            'POST',
            '/api/v1/repository/notes',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'name' => $name,
                'branch' => 'main',
                'schemaVersion' => '1.0.0',
                'data' => [
                    'title' => $name,
                    'content' => 'Initial content',
                ],
            ], JSON_THROW_ON_ERROR)
        );
        $this->assertResponseStatusCodeSame(201);
        $content = $this->client->getResponse()->getContent();
        $response = json_decode(is_string($content) ? $content : '', true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);

        return $this->stringKeyedArray($response);
    }

    /**
     * @param array<string, mixed> $document
     */
    private function jsonApiId(array $document): string
    {
        $this->assertArrayHasKey('data', $document);
        $this->assertIsArray($document['data']);
        $this->assertArrayHasKey('id', $document['data']);
        $this->assertIsString($document['data']['id']);

        return $document['data']['id'];
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
