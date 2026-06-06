<?php

declare(strict_types=1);

namespace Bareapi\Tests\Feature\MetastoreParity;

use Bareapi\Tests\Feature\FeatureTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

final class RevisionLifecycleTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        $this->migrateDatabase();
        parent::setUp();
    }

    public function testCreatePatchAndFetchRevisionLifecycle(): void
    {
        $created = $this->requestJsonApi('POST', '/api/v1/repository/notes', [
            'name' => 'Revisioned Note',
            'branch' => 'main',
            'schemaVersion' => '1.0.0',
            'data' => [
                'title' => 'Initial title',
                'content' => 'Initial content',
            ],
        ], 201);
        $id = $this->jsonApiId($created);
        $this->assertSame(1, $this->jsonApiRevision($created));

        sleep(1);
        $patched = $this->requestJsonApi('PATCH', '/api/v1/repository/notes/' . $id, [
            'data' => [
                'content' => 'Updated content',
            ],
        ], 200);
        $this->assertSame(2, $this->jsonApiRevision($patched));
        $this->assertSame('Updated content', $this->jsonApiAttributes($patched)['content']);
        $this->assertNotSame(
            $this->jsonApiRevisionCreatedAt($created),
            $this->jsonApiRevisionCreatedAt($patched)
        );

        $firstRevision = $this->requestJsonApi('GET', '/api/v1/repository/notes/' . $id . '/revisions/1', null, 200);
        $this->assertSame(1, $this->jsonApiRevision($firstRevision));
        $this->assertSame(
            $this->jsonApiRevisionCreatedAt($created),
            $this->jsonApiRevisionCreatedAt($firstRevision)
        );
        $this->assertSame([
            'title' => 'Initial title',
            'content' => 'Initial content',
        ], $this->jsonApiAttributes($firstRevision));
    }

    public function testListsRepositoryRevisions(): void
    {
        $created = $this->requestJsonApi('POST', '/api/v1/repository/notes', [
            'name' => 'Listed Revision Note',
            'branch' => 'main',
            'schemaVersion' => '1.0.0',
            'data' => [
                'title' => 'Initial title',
                'content' => 'Initial content',
            ],
        ], 201);
        $id = $this->jsonApiId($created);

        sleep(1);
        $this->requestJsonApi('PATCH', '/api/v1/repository/notes/' . $id, [
            'data' => [
                'content' => 'Updated content',
            ],
        ], 200);

        $listed = $this->requestJsonApi('GET', '/api/v1/repository/notes/revisions?name=Listed%20Revision%20Note', null, 200);

        $this->assertArrayHasKey('data', $listed);
        $this->assertIsArray($listed['data']);
        $this->assertCount(2, $listed['data']);
        $this->assertSame([1, 2], array_map(
            static function (mixed $item): int {
                self::assertIsArray($item);
                self::assertArrayHasKey('meta', $item);
                self::assertIsArray($item['meta']);
                self::assertArrayHasKey('revision', $item['meta']);
                self::assertIsInt($item['meta']['revision']);

                return $item['meta']['revision'];
            },
            $listed['data']
        ));
        $this->assertNotSame(
            $this->collectionRevisionCreatedAt($listed, 0),
            $this->collectionRevisionCreatedAt($listed, 1)
        );
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<string, mixed>
     */
    private function requestJsonApi(string $method, string $path, ?array $payload, int $expectedStatus): array
    {
        $this->client->request(
            $method,
            $path,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame($expectedStatus);
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
        $data = $this->jsonApiData($document);
        $this->assertArrayHasKey('id', $data);
        $this->assertIsString($data['id']);

        return $data['id'];
    }

    /**
     * @param array<string, mixed> $document
     */
    private function jsonApiRevision(array $document): int
    {
        $data = $this->jsonApiData($document);
        $this->assertArrayHasKey('meta', $data);
        $this->assertIsArray($data['meta']);
        $this->assertArrayHasKey('revision', $data['meta']);
        $this->assertIsInt($data['meta']['revision']);

        return $data['meta']['revision'];
    }

    /**
     * @param array<string, mixed> $document
     */
    private function jsonApiRevisionCreatedAt(array $document): string
    {
        $data = $this->jsonApiData($document);
        $this->assertArrayHasKey('meta', $data);
        $this->assertIsArray($data['meta']);
        $this->assertArrayHasKey('revisionCreatedAt', $data['meta']);
        $this->assertIsString($data['meta']['revisionCreatedAt']);

        return $data['meta']['revisionCreatedAt'];
    }

    /**
     * @param array<string, mixed> $document
     */
    private function collectionRevisionCreatedAt(array $document, int $index): string
    {
        $this->assertArrayHasKey('data', $document);
        $this->assertIsArray($document['data']);
        $this->assertArrayHasKey($index, $document['data']);
        $this->assertIsArray($document['data'][$index]);
        $this->assertArrayHasKey('meta', $document['data'][$index]);
        $this->assertIsArray($document['data'][$index]['meta']);
        $this->assertArrayHasKey('revisionCreatedAt', $document['data'][$index]['meta']);
        $this->assertIsString($document['data'][$index]['meta']['revisionCreatedAt']);

        return $document['data'][$index]['meta']['revisionCreatedAt'];
    }

    /**
     * @param array<string, mixed> $document
     * @return array<string, mixed>
     */
    private function jsonApiAttributes(array $document): array
    {
        $data = $this->jsonApiData($document);
        $this->assertArrayHasKey('attributes', $data);
        $this->assertIsArray($data['attributes']);

        return $this->stringKeyedArray($data['attributes']);
    }

    /**
     * @param array<string, mixed> $document
     * @return array<string, mixed>
     */
    private function jsonApiData(array $document): array
    {
        $this->assertArrayHasKey('data', $document);
        $this->assertIsArray($document['data']);

        return $this->stringKeyedArray($document['data']);
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
