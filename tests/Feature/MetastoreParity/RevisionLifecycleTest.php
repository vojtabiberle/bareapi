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

        $patched = $this->requestJsonApi('PATCH', '/api/v1/repository/notes/' . $id, [
            'data' => [
                'content' => 'Updated content',
            ],
        ], 200);
        $this->assertSame(2, $this->jsonApiRevision($patched));
        $this->assertSame('Updated content', $this->jsonApiAttributes($patched)['content']);

        $firstRevision = $this->requestJsonApi('GET', '/api/v1/repository/notes/' . $id . '/revisions/1', null, 200);
        $this->assertSame(1, $this->jsonApiRevision($firstRevision));
        $this->assertSame([
            'title' => 'Initial title',
            'content' => 'Initial content',
        ], $this->jsonApiAttributes($firstRevision));
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
