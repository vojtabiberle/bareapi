<?php

declare(strict_types=1);

namespace Bareapi\Tests\Feature\MetastoreParity;

use Bareapi\Repository\SchemaRepository;
use Bareapi\Tests\Feature\FeatureTestCase;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

final class AuthorizationTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        $this->migrateDatabase();
        parent::setUp();

        $connection = self::getContainer()->get(Connection::class);
        $this->assertInstanceOf(Connection::class, $connection);
        $connection->executeStatement('TRUNCATE TABLE schemas RESTART IDENTITY CASCADE');

        $repository = self::getContainer()->get(SchemaRepository::class);
        $this->assertInstanceOf(SchemaRepository::class, $repository);
        $repository->save('notes', '1.0.0', true, [
            'title' => 'notes',
            'type' => 'object',
            'x-bareapi.acl' => [
                'create' => ['object:create'],
                'update' => ['object:update'],
                'delete' => ['object:delete'],
            ],
        ]);
    }

    public function testProtectedCreateRequiresBearerPermission(): void
    {
        $this->requestCreate(null);
        $this->assertResponseStatusCodeSame(401);

        $this->requestCreate('Bearer object:update');
        $this->assertResponseStatusCodeSame(403);

        $this->requestCreate('Bearer object:create');
        $this->assertResponseStatusCodeSame(201);
    }

    public function testReadsRemainPublicWhenWritesAreProtected(): void
    {
        $this->requestCreate('Bearer object:create');
        $this->assertResponseStatusCodeSame(201);
        $content = $this->client->getResponse()->getContent();
        $response = json_decode(is_string($content) ? $content : '', true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('data', $response);
        $this->assertIsArray($response['data']);
        $this->assertArrayHasKey('id', $response['data']);
        $this->assertIsString($response['data']['id']);

        $this->client->request('GET', '/api/v1/repository/notes/' . $response['data']['id']);
        $this->assertResponseStatusCodeSame(200);
    }

    private function requestCreate(?string $authorization): void
    {
        $server = [
            'CONTENT_TYPE' => 'application/json',
        ];
        if ($authorization !== null) {
            $server['HTTP_AUTHORIZATION'] = $authorization;
        }

        $this->client->request(
            'POST',
            '/api/v1/repository/notes',
            [],
            [],
            $server,
            json_encode([
                'name' => 'Protected note',
                'branch' => 'main',
                'schemaVersion' => '1.0.0',
                'data' => [
                    'title' => 'Protected',
                    'content' => 'Protected content',
                ],
            ], JSON_THROW_ON_ERROR)
        );
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
