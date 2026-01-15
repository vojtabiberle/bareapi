<?php

declare(strict_types=1);

namespace Bareapi\Tests\Feature\Api;

use Bareapi\Entity\MetaObject;
use Bareapi\Entity\MetaObjectRevision;
use Bareapi\Entity\Schema;
use Bareapi\Security\ApiKeyAuthenticator;
use Bareapi\Tests\Factory\MetaObjectFactory;
use Bareapi\Tests\Factory\SchemaFactory;
use Bareapi\Tests\Feature\FeatureTestCase;
use Doctrine\ORM\EntityManagerInterface;

class RepositoryControllerTest extends FeatureTestCase
{
    private const CONTENT_TYPE = 'application/vnd.api+json';

    private EntityManagerInterface $em;
    private string $apiKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->apiKey = $this->getApiKeyFromContainer();
    }

    /**
     * Get the API key from the container's ApiKeyAuthenticator.
     * This ensures tests use the same key the authenticator expects.
     */
    private function getApiKeyFromContainer(): string
    {
        $authenticator = self::getContainer()->get(ApiKeyAuthenticator::class);
        $reflection = new \ReflectionClass($authenticator);
        $property = $reflection->getProperty('apiKey');

        return $property->getValue($authenticator);
    }

    // ==================== LIST Tests ====================

    public function testListReturnsEmptyArrayWhenNoObjects(): void
    {
        $this->createNotesSchema();

        $this->client->request(
            'GET',
            '/api/v1/repository/notes',
            [],
            [],
            $this->authHeaders()
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', self::CONTENT_TYPE);

        $data = $this->getJsonResponse();
        $this->assertArrayHasKey('data', $data);
        $this->assertSame([], $data['data']);
    }

    public function testListReturnsMetaObjects(): void
    {
        $this->createNotesSchema();
        $this->createAndPersistMetaObject('note-1');
        $this->createAndPersistMetaObject('note-2');

        $this->client->request(
            'GET',
            '/api/v1/repository/notes',
            [],
            [],
            $this->authHeaders()
        );

        $this->assertResponseIsSuccessful();
        $data = $this->getJsonResponse();
        $this->assertCount(2, $data['data']);
    }

    public function testListFiltersbyProjectId(): void
    {
        $this->createNotesSchema();
        $this->createAndPersistMetaObject('note-1', projectId: 100);
        $this->createAndPersistMetaObject('note-2', projectId: 200);

        $this->client->request(
            'GET',
            '/api/v1/repository/notes',
            [],
            [],
            array_merge($this->authHeaders(), ['HTTP_X-Project-ID' => '100'])
        );

        $this->assertResponseIsSuccessful();
        $data = $this->getJsonResponse();
        $this->assertCount(1, $data['data']);
        $this->assertSame('note-1', $data['data'][0]['attributes']['name']);
    }

    public function testListReturns404ForUnknownSchema(): void
    {
        $this->client->request(
            'GET',
            '/api/v1/repository/unknown-type',
            [],
            [],
            $this->authHeaders()
        );

        $this->assertResponseStatusCodeSame(404);
    }

    public function testListRequiresAuthentication(): void
    {
        $this->createNotesSchema();

        $this->client->request('GET', '/api/v1/repository/notes');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testListRejectsInvalidApiKey(): void
    {
        $this->createNotesSchema();

        $this->client->request(
            'GET',
            '/api/v1/repository/notes',
            [],
            [],
            ['HTTP_X-API-Key' => 'invalid-key']
        );

        $this->assertResponseStatusCodeSame(401);
    }

    // ==================== CREATE Tests ====================

    public function testCreateMetaObjectSuccessfully(): void
    {
        $this->createNotesSchema();

        $this->client->request(
            'POST',
            '/api/v1/repository/notes',
            [],
            [],
            array_merge($this->authHeaders(), [
                'HTTP_X-Project-ID' => '123',
                'HTTP_X-Organization-ID' => 'org-abc',
                'CONTENT_TYPE' => 'application/json',
            ]),
            json_encode([
                'name' => 'my-note',
                'data' => ['title' => 'Test Note', 'content' => 'Hello World'],
            ])
        );

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('Content-Type', self::CONTENT_TYPE);

        $data = $this->getJsonResponse();
        $this->assertSame('notes', $data['data']['type']);
        $this->assertSame('my-note', $data['data']['attributes']['name']);
        $this->assertSame(123, $data['data']['attributes']['projectId']);
    }

    public function testCreateSetsOrganizationIdFromHeader(): void
    {
        $this->createNotesSchema();

        $this->client->request(
            'POST',
            '/api/v1/repository/notes',
            [],
            [],
            array_merge($this->authHeaders(), [
                'HTTP_X-Organization-ID' => 'my-org',
                'CONTENT_TYPE' => 'application/json',
            ]),
            json_encode([
                'name' => 'org-note',
                'data' => ['title' => 'Org Note'],
            ])
        );

        $this->assertResponseStatusCodeSame(201);
        $data = $this->getJsonResponse();
        $this->assertSame('my-org', $data['data']['attributes']['organizationId']);
    }

    public function testCreateWithCustomBranch(): void
    {
        $this->createNotesSchema();

        $this->client->request(
            'POST',
            '/api/v1/repository/notes',
            [],
            [],
            array_merge($this->authHeaders(), ['CONTENT_TYPE' => 'application/json']),
            json_encode([
                'name' => 'branch-note',
                'data' => ['title' => 'Branch Note'],
                'branch' => 'feature-x',
            ])
        );

        $this->assertResponseStatusCodeSame(201);
        $data = $this->getJsonResponse();
        $this->assertSame('feature-x', $data['data']['attributes']['branch']);
    }

    public function testCreateReturns404ForUnknownSchema(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/repository/unknown',
            [],
            [],
            array_merge($this->authHeaders(), ['CONTENT_TYPE' => 'application/json']),
            json_encode(['name' => 'test', 'data' => []])
        );

        $this->assertResponseStatusCodeSame(404);
    }

    public function testCreateReturnsValidationError(): void
    {
        $this->createNotesSchema();

        $this->client->request(
            'POST',
            '/api/v1/repository/notes',
            [],
            [],
            array_merge($this->authHeaders(), ['CONTENT_TYPE' => 'application/json']),
            json_encode([
                'name' => 'invalid-note',
                'data' => ['content' => 'Missing required title'],
            ])
        );

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getJsonResponse();
        $this->assertArrayHasKey('errors', $data);
    }

    public function testCreateReturnsConflictForDuplicateName(): void
    {
        $this->createNotesSchema();
        $this->createAndPersistMetaObject('duplicate-name');

        $this->client->request(
            'POST',
            '/api/v1/repository/notes',
            [],
            [],
            array_merge($this->authHeaders(), [
                'HTTP_X-Organization-ID' => 'org-123',
                'CONTENT_TYPE' => 'application/json',
            ]),
            json_encode([
                'name' => 'duplicate-name',
                'data' => ['title' => 'Another Note'],
            ])
        );

        $this->assertResponseStatusCodeSame(409);
    }

    public function testCreateRequiresAuthentication(): void
    {
        $this->createNotesSchema();

        $this->client->request(
            'POST',
            '/api/v1/repository/notes',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'test', 'data' => ['title' => 'Test']])
        );

        $this->assertResponseStatusCodeSame(401);
    }

    // ==================== GET Tests ====================

    public function testGetMetaObjectSuccessfully(): void
    {
        $this->createNotesSchema();
        $metaObject = $this->createAndPersistMetaObject('get-test');

        $this->client->request(
            'GET',
            '/api/v1/repository/notes/' . $metaObject->getUuid()->toString(),
            [],
            [],
            $this->authHeaders()
        );

        $this->assertResponseIsSuccessful();
        $data = $this->getJsonResponse();
        $this->assertSame('get-test', $data['data']['attributes']['name']);
    }

    public function testGetReturns404ForNonExistent(): void
    {
        $this->createNotesSchema();

        $this->client->request(
            'GET',
            '/api/v1/repository/notes/00000000-0000-0000-0000-000000000000',
            [],
            [],
            $this->authHeaders()
        );

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetReturns404ForTypeMismatch(): void
    {
        $this->createNotesSchema();
        $this->createSchema('articles');
        $metaObject = $this->createAndPersistMetaObject('type-mismatch');

        $this->client->request(
            'GET',
            '/api/v1/repository/articles/' . $metaObject->getUuid()->toString(),
            [],
            [],
            $this->authHeaders()
        );

        $this->assertResponseStatusCodeSame(404);
    }

    // ==================== PATCH Tests ====================

    public function testPatchMergesDataSuccessfully(): void
    {
        $this->createNotesSchema();
        $metaObject = $this->createAndPersistMetaObject('patch-test', data: [
            'title' => 'Original Title',
            'content' => 'Original Content',
        ]);

        $this->client->request(
            'PATCH',
            '/api/v1/repository/notes/' . $metaObject->getUuid()->toString(),
            [],
            [],
            array_merge($this->authHeaders(), ['CONTENT_TYPE' => 'application/json']),
            json_encode([
                'data' => ['content' => 'Updated Content'],
            ])
        );

        $this->assertResponseIsSuccessful();
        $data = $this->getJsonResponse();
        $this->assertSame('Original Title', $data['data']['attributes']['data']['title']);
        $this->assertSame('Updated Content', $data['data']['attributes']['data']['content']);
    }

    public function testPatchCreatesNewRevision(): void
    {
        $this->createNotesSchema();
        $metaObject = $this->createAndPersistMetaObject('revision-test');

        $this->client->request(
            'PATCH',
            '/api/v1/repository/notes/' . $metaObject->getUuid()->toString(),
            [],
            [],
            array_merge($this->authHeaders(), ['CONTENT_TYPE' => 'application/json']),
            json_encode([
                'data' => ['content' => 'New content'],
            ])
        );

        $this->assertResponseIsSuccessful();
        $data = $this->getJsonResponse();
        $this->assertSame(2, $data['data']['attributes']['revision']);
    }

    public function testPatchReturns404ForNonExistent(): void
    {
        $this->createNotesSchema();

        $this->client->request(
            'PATCH',
            '/api/v1/repository/notes/00000000-0000-0000-0000-000000000000',
            [],
            [],
            array_merge($this->authHeaders(), ['CONTENT_TYPE' => 'application/json']),
            json_encode(['data' => ['title' => 'Test']])
        );

        $this->assertResponseStatusCodeSame(404);
    }

    public function testPatchReturnsValidationError(): void
    {
        $this->createNotesSchema();
        $metaObject = $this->createAndPersistMetaObject('validation-test', data: [
            'title' => 'Valid Title',
        ]);

        // Create a schema that requires title to be a string
        // When we patch with an invalid type, it should fail validation
        $this->client->request(
            'PATCH',
            '/api/v1/repository/notes/' . $metaObject->getUuid()->toString(),
            [],
            [],
            array_merge($this->authHeaders(), ['CONTENT_TYPE' => 'application/json']),
            json_encode([
                'data' => ['title' => null], // null will fail validation
            ])
        );

        // The merged data will have title as null, which should fail validation
        $this->assertResponseStatusCodeSame(422);
    }

    // ==================== PUT Tests ====================

    public function testPutReplacesDataSuccessfully(): void
    {
        $this->createNotesSchema();
        $metaObject = $this->createAndPersistMetaObject('put-test', data: [
            'title' => 'Old Title',
            'content' => 'Old Content',
        ]);

        $this->client->request(
            'PUT',
            '/api/v1/repository/notes/' . $metaObject->getUuid()->toString(),
            [],
            [],
            array_merge($this->authHeaders(), ['CONTENT_TYPE' => 'application/json']),
            json_encode([
                'name' => 'updated-name',
                'data' => ['title' => 'New Title'],
            ])
        );

        $this->assertResponseIsSuccessful();
        $data = $this->getJsonResponse();
        $this->assertSame('updated-name', $data['data']['attributes']['name']);
        $this->assertSame('New Title', $data['data']['attributes']['data']['title']);
        $this->assertArrayNotHasKey('content', $data['data']['attributes']['data']);
    }

    public function testPutCreatesNewRevision(): void
    {
        $this->createNotesSchema();
        $metaObject = $this->createAndPersistMetaObject('put-revision-test');

        $this->client->request(
            'PUT',
            '/api/v1/repository/notes/' . $metaObject->getUuid()->toString(),
            [],
            [],
            array_merge($this->authHeaders(), ['CONTENT_TYPE' => 'application/json']),
            json_encode([
                'name' => 'new-name',
                'data' => ['title' => 'Updated'],
            ])
        );

        $this->assertResponseIsSuccessful();
        $data = $this->getJsonResponse();
        $this->assertSame(2, $data['data']['attributes']['revision']);
    }

    public function testPutReturns404ForNonExistent(): void
    {
        $this->createNotesSchema();

        $this->client->request(
            'PUT',
            '/api/v1/repository/notes/00000000-0000-0000-0000-000000000000',
            [],
            [],
            array_merge($this->authHeaders(), ['CONTENT_TYPE' => 'application/json']),
            json_encode(['name' => 'test', 'data' => ['title' => 'Test']])
        );

        $this->assertResponseStatusCodeSame(404);
    }

    // ==================== DELETE Tests ====================

    public function testDeleteSoftDeletesSuccessfully(): void
    {
        $this->createNotesSchema();
        $metaObject = $this->createAndPersistMetaObject('delete-test');
        $uuid = $metaObject->getUuid()->toString();

        $this->client->request(
            'DELETE',
            '/api/v1/repository/notes/' . $uuid,
            [],
            [],
            $this->authHeaders()
        );

        $this->assertResponseStatusCodeSame(204);

        // Verify it's not accessible anymore
        $this->client->request(
            'GET',
            '/api/v1/repository/notes/' . $uuid,
            [],
            [],
            $this->authHeaders()
        );
        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteReturns404ForNonExistent(): void
    {
        $this->createNotesSchema();

        $this->client->request(
            'DELETE',
            '/api/v1/repository/notes/00000000-0000-0000-0000-000000000000',
            [],
            [],
            $this->authHeaders()
        );

        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteRequiresAuthentication(): void
    {
        $this->createNotesSchema();
        $metaObject = $this->createAndPersistMetaObject('auth-delete-test');

        $this->client->request(
            'DELETE',
            '/api/v1/repository/notes/' . $metaObject->getUuid()->toString()
        );

        $this->assertResponseStatusCodeSame(401);
    }

    // ==================== Revision Tests ====================

    public function testGetRevisionSuccessfully(): void
    {
        $this->createNotesSchema();
        $metaObject = $this->createAndPersistMetaObject('revision-get-test');

        $this->client->request(
            'GET',
            '/api/v1/repository/notes/' . $metaObject->getUuid()->toString() . '/revisions/1',
            [],
            [],
            $this->authHeaders()
        );

        $this->assertResponseIsSuccessful();
        $data = $this->getJsonResponse();
        $this->assertSame(1, $data['data']['attributes']['revision']);
    }

    public function testGetRevisionReturns404ForNonExistent(): void
    {
        $this->createNotesSchema();
        $metaObject = $this->createAndPersistMetaObject('revision-404-test');

        $this->client->request(
            'GET',
            '/api/v1/repository/notes/' . $metaObject->getUuid()->toString() . '/revisions/999',
            [],
            [],
            $this->authHeaders()
        );

        $this->assertResponseStatusCodeSame(404);
    }

    // ==================== Helper Methods ====================

    /**
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        return [
            'HTTP_X-API-Key' => $this->apiKey,
            'HTTP_X-Organization-ID' => 'org-123',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getJsonResponse(): array
    {
        $content = $this->client->getResponse()->getContent();
        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function createNotesSchema(): Schema
    {
        return $this->createSchema('notes');
    }

    private function createSchema(string $objectType, string $version = '1.0.0'): Schema
    {
        $schema = SchemaFactory::create($objectType, $version);
        $this->em->persist($schema);
        $this->em->flush();

        return $schema;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createAndPersistMetaObject(
        string $name,
        ?int $projectId = 123,
        array $data = ['title' => 'Test', 'content' => 'Sample'],
    ): MetaObject {
        $metaObject = MetaObjectFactory::create(
            data: $data,
            name: $name,
            projectId: $projectId,
        );
        $this->em->persist($metaObject);
        $this->em->flush();

        return $metaObject;
    }
}
