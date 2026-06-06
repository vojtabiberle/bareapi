<?php

declare(strict_types=1);

namespace Bareapi\Tests\Feature\MetastoreParity;

use Bareapi\Tests\Feature\FeatureTestCase;
use Doctrine\DBAL\Connection;

final class RepositoryRoutesTest extends FeatureTestCase
{
    public function testRepositoryObjectLifecycleRoutesAreAvailable(): void
    {
        $created = $this->requestJsonApi(
            'POST',
            '/api/v1/repository/notes',
            [
                'name' => 'Release Notes',
                'branch' => 'main',
                'schemaVersion' => '1.0.0',
                'data' => [
                    'title' => 'Initial',
                    'content' => 'Initial content',
                ],
            ],
            201
        );
        $id = $this->jsonApiId($created);

        $fetched = $this->requestJsonApi('GET', '/api/v1/repository/notes/' . $id, null, 200);
        $this->assertSame([
            'title' => 'Initial',
            'content' => 'Initial content',
        ], $this->jsonApiAttributes($fetched));

        $patched = $this->requestJsonApi(
            'PATCH',
            '/api/v1/repository/notes/' . $id,
            [
                'data' => [
                    'content' => 'Patched content',
                ],
            ],
            200
        );
        $this->assertSame([
            'title' => 'Initial',
            'content' => 'Patched content',
        ], $this->jsonApiAttributes($patched));

        $put = $this->requestJsonApi(
            'PUT',
            '/api/v1/repository/notes/' . $id,
            [
                'data' => [
                    'title' => 'Replacement',
                    'content' => 'Replacement content',
                ],
            ],
            200
        );
        $this->assertSame([
            'title' => 'Replacement',
            'content' => 'Replacement content',
        ], $this->jsonApiAttributes($put));

        $this->client->request('DELETE', '/api/v1/repository/notes/' . $id);
        $this->assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/v1/repository/notes/' . $id);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testRepositoryCreateRejectsMalformedJson(): void
    {
        $this->requestMalformedJson('POST', '/api/v1/repository/notes');

        $this->assertResponseStatusCodeSame(400);
    }

    public function testRepositoryPatchRejectsMalformedJson(): void
    {
        $created = $this->requestJsonApi(
            'POST',
            '/api/v1/repository/notes',
            [
                'name' => 'Patch Invalid JSON',
                'data' => [
                    'title' => 'Initial',
                    'content' => 'Initial content',
                ],
            ],
            201
        );

        $this->requestMalformedJson('PATCH', '/api/v1/repository/notes/' . $this->jsonApiId($created));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testRepositoryPutRejectsMalformedJson(): void
    {
        $created = $this->requestJsonApi(
            'POST',
            '/api/v1/repository/notes',
            [
                'name' => 'Put Invalid JSON',
                'data' => [
                    'title' => 'Initial',
                    'content' => 'Initial content',
                ],
            ],
            201
        );

        $this->requestMalformedJson('PUT', '/api/v1/repository/notes/' . $this->jsonApiId($created));

        $this->assertResponseStatusCodeSame(400);
    }

    public function testRepositoryPatchRejectsTopLevelMetadata(): void
    {
        $created = $this->requestJsonApi(
            'POST',
            '/api/v1/repository/notes',
            [
                'name' => 'Patch Metadata',
                'data' => [
                    'title' => 'Initial',
                    'content' => 'Initial content',
                ],
            ],
            201
        );

        $response = $this->requestJsonApi(
            'PATCH',
            '/api/v1/repository/notes/' . $this->jsonApiId($created),
            [
                'name' => 'Ignored name',
                'data' => [
                    'content' => 'Updated content',
                ],
            ],
            400
        );

        $this->assertSame('Repository metadata cannot be updated', $response['error'] ?? null);
    }

    public function testRepositoryPutRejectsTopLevelMetadata(): void
    {
        $created = $this->requestJsonApi(
            'POST',
            '/api/v1/repository/notes',
            [
                'name' => 'Put Metadata',
                'data' => [
                    'title' => 'Initial',
                    'content' => 'Initial content',
                ],
            ],
            201
        );

        $response = $this->requestJsonApi(
            'PUT',
            '/api/v1/repository/notes/' . $this->jsonApiId($created),
            [
                'schemaVersion' => '1.0.1',
                'data' => [
                    'title' => 'Replacement',
                    'content' => 'Replacement content',
                ],
            ],
            400
        );

        $this->assertSame('Repository metadata cannot be updated', $response['error'] ?? null);
    }

    public function testRepositoryShowUsesNeutralObjectTypeForLegacyTypedRows(): void
    {
        $id = $this->insertLegacyTypedNote();

        $fetched = $this->requestJsonApi('GET', '/api/v1/repository/notes/' . $id, null, 200);

        $this->assertSame('notes', $this->jsonApiType($fetched));
        $this->assertSame([
            'title' => 'Legacy typed note',
            'content' => 'Legacy content',
        ], $this->jsonApiAttributes($fetched));
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

    private function requestMalformedJson(string $method, string $path): void
    {
        $this->client->request(
            $method,
            $path,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            '{"data":'
        );
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

    /**
     * @param array<string, mixed> $document
     */
    private function jsonApiType(array $document): string
    {
        $this->assertArrayHasKey('data', $document);
        $this->assertIsArray($document['data']);
        $this->assertArrayHasKey('type', $document['data']);
        $this->assertIsString($document['data']['type']);

        return $document['data']['type'];
    }

    /**
     * @param array<string, mixed> $document
     * @return array<string, mixed>
     */
    private function jsonApiAttributes(array $document): array
    {
        $this->assertArrayHasKey('data', $document);
        $this->assertIsArray($document['data']);
        $this->assertArrayHasKey('attributes', $document['data']);
        $this->assertIsArray($document['data']['attributes']);

        return $this->stringKeyedArray($document['data']['attributes']);
    }

    private function insertLegacyTypedNote(): string
    {
        $connection = self::getContainer()->get(Connection::class);
        $this->assertInstanceOf(Connection::class, $connection);
        $id = '018ff3ae-c558-7ed8-8f68-0242ac1200ca';
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $data = [
            'title' => 'Legacy typed note',
            'content' => 'Legacy content',
        ];

        $connection->insert('meta_objects', [
            'id' => $id,
            'type' => 'legacy_notes',
            'object_type' => 'notes',
            'schema_version' => '1.0.0',
            'branch' => 'main',
            'name' => 'Legacy typed note',
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

        return $id;
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
