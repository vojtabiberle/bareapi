<?php

declare(strict_types=1);

namespace Bareapi\Tests\Feature\MetastoreParity;

use Bareapi\Repository\SchemaRepository;
use Bareapi\Tests\Feature\FeatureTestCase;
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
