<?php

declare(strict_types=1);

namespace Bareapi\Tests\Feature\MetastoreParity;

use Bareapi\Repository\SchemaRepository;
use Bareapi\Tests\Feature\FeatureTestCase;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

final class ListFilteringTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        $this->migrateDatabase();
        parent::setUp();

        $repository = self::getContainer()->get(SchemaRepository::class);
        $this->assertInstanceOf(SchemaRepository::class, $repository);
        $repository->save('tags', '1.0.0', true, [
            'title' => 'tags',
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'x-filterable' => true,
                ],
                'color' => [
                    'type' => 'string',
                    'x-filterable' => true,
                ],
                'creator' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'x-filterable' => true,
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testListsRepositoryObjectsWithFiltersOrderingLimitAndOffset(): void
    {
        $this->createTag('Alpha', 'red', 'Jan');
        $this->createTag('Bravo', 'blue', 'Peter');
        $this->createTag('Charlie', 'red', 'Jan');

        $filtered = $this->listTags('color=red&creator.name=Jan&name%5Border%5D=desc&limit=1&offset=0');

        $this->assertSame(['Charlie'], $this->names($filtered));
    }

    public function testRejectsUnknownFilter(): void
    {
        $this->createTag('Alpha', 'red', 'Jan');

        $this->client->request('GET', '/api/v1/repository/tags?unknown=value');
        $this->assertResponseStatusCodeSame(400);
    }

    public function testRejectsInvalidTableFieldFilterValueForObjectList(): void
    {
        $this->createTag('Alpha', 'red', 'Jan');

        $this->client->request('GET', '/api/v1/repository/tags?revision=bad');
        $this->assertResponseStatusCodeSame(400);
    }

    public function testRejectsInvalidTableFieldFilterValueForRevisionList(): void
    {
        $this->createTag('Alpha', 'red', 'Jan');

        $this->client->request('GET', '/api/v1/repository/tags/revisions?revision=bad');
        $this->assertResponseStatusCodeSame(400);
    }

    public function testListsRepositoryObjectsByNeutralObjectTypeColumn(): void
    {
        $this->insertLegacyTypedTag();

        $listed = $this->listTags('');

        $this->assertSame(['Legacy typed tag'], $this->names($listed));
        $this->assertSame(['tags'], $this->types($listed));
    }

    public function testListsRepositoryRevisionsByNeutralObjectTypeColumn(): void
    {
        $this->insertLegacyTypedTag();

        $listed = $this->listTagRevisions();

        $this->assertSame(['Legacy typed tag'], $this->names($listed));
        $this->assertSame(['tags'], $this->types($listed));
    }

    private function createTag(string $name, string $color, string $creatorName): void
    {
        $this->client->request(
            'POST',
            '/api/v1/repository/tags',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'name' => $name,
                'data' => [
                    'id' => '018ff3ae-c558-7ed8-8f68-0242ac120002',
                    'name' => $name,
                    'color' => $color,
                    'creator' => [
                        'id' => '018ff3ae-c558-7ed8-8f68-0242ac120003',
                        'name' => $creatorName,
                    ],
                ],
            ], JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame(201);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listTags(string $query): array
    {
        $this->client->request('GET', '/api/v1/repository/tags?' . $query);
        $this->assertResponseStatusCodeSame(200);

        $content = $this->client->getResponse()->getContent();
        $response = json_decode(is_string($content) ? $content : '', true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('data', $response);
        $this->assertIsArray($response['data']);

        return array_values(array_map(
            fn (array $item): array => $this->stringKeyedArray($item),
            array_filter($response['data'], static fn (mixed $item): bool => is_array($item))
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listTagRevisions(): array
    {
        $this->client->request('GET', '/api/v1/repository/tags/revisions');
        $this->assertResponseStatusCodeSame(200);

        $content = $this->client->getResponse()->getContent();
        $response = json_decode(is_string($content) ? $content : '', true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('data', $response);
        $this->assertIsArray($response['data']);

        return array_values(array_map(
            fn (array $item): array => $this->stringKeyedArray($item),
            array_filter($response['data'], static fn (mixed $item): bool => is_array($item))
        ));
    }

    private function insertLegacyTypedTag(): void
    {
        $connection = self::getContainer()->get(Connection::class);
        $this->assertInstanceOf(Connection::class, $connection);
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $id = '018ff3ae-c558-7ed8-8f68-0242ac1200bb';
        $data = [
            'id' => '018ff3ae-c558-7ed8-8f68-0242ac1200bc',
            'name' => 'legacy-typed-tag',
            'color' => 'red',
            'creator' => [
                'id' => '018ff3ae-c558-7ed8-8f68-0242ac1200bd',
                'name' => 'Jan',
            ],
        ];

        $connection->insert('meta_objects', [
            'id' => $id,
            'type' => 'legacy_tags',
            'object_type' => 'tags',
            'schema_version' => '1.0.0',
            'branch' => 'main',
            'name' => 'Legacy typed tag',
            'data' => json_encode($data, JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
            'last_updated' => $now,
        ]);
        $connection->insert('meta_object_revisions', [
            'uuid' => $id,
            'revision' => 1,
            'data' => json_encode($data, JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, string>
     */
    private function names(array $items): array
    {
        return array_map(static function (array $item): string {
            self::assertArrayHasKey('meta', $item);
            self::assertIsArray($item['meta']);
            self::assertArrayHasKey('name', $item['meta']);
            self::assertIsString($item['meta']['name']);

            return $item['meta']['name'];
        }, $items);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, string>
     */
    private function types(array $items): array
    {
        return array_map(static function (array $item): string {
            self::assertArrayHasKey('type', $item);
            self::assertIsString($item['type']);

            return $item['type'];
        }, $items);
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
