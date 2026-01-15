<?php

declare(strict_types=1);

namespace Bareapi\Tests\Feature\Api;

use Bareapi\Tests\Factory\SchemaFactory;
use Bareapi\Tests\Feature\FeatureTestCase;
use Doctrine\ORM\EntityManagerInterface;

class SchemaControllerTest extends FeatureTestCase
{
    private const CONTENT_TYPE = 'application/vnd.api+json';

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    // ==================== Get Default Schema Tests ====================

    public function testGetDefaultSchemaSuccessfully(): void
    {
        $schema = SchemaFactory::create('notes', '1.0.0', isDefault: true, description: 'Notes schema');
        $this->em->persist($schema);
        $this->em->flush();

        $this->client->request('GET', '/api/v1/schema/notes');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', self::CONTENT_TYPE);

        $data = $this->getJsonResponse();
        $this->assertSame('schemas', $data['data']['type']);
        $this->assertSame('notes-1.0.0', $data['data']['id']);
        $this->assertSame('notes', $data['data']['attributes']['objectType']);
        $this->assertSame('1.0.0', $data['data']['attributes']['version']);
        $this->assertTrue($data['data']['attributes']['isDefault']);
        $this->assertSame('Notes schema', $data['data']['attributes']['description']);
    }

    public function testGetDefaultSchemaReturnsJsonApiFormat(): void
    {
        $schema = SchemaFactory::create('articles');
        $this->em->persist($schema);
        $this->em->flush();

        $this->client->request('GET', '/api/v1/schema/articles');

        $this->assertResponseIsSuccessful();
        $data = $this->getJsonResponse();

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('type', $data['data']);
        $this->assertArrayHasKey('id', $data['data']);
        $this->assertArrayHasKey('attributes', $data['data']);
        $this->assertArrayHasKey('schema', $data['data']['attributes']);
        $this->assertArrayHasKey('createdAt', $data['data']['attributes']);
        $this->assertArrayHasKey('updatedAt', $data['data']['attributes']);
    }

    public function testGetDefaultSchemaReturns404ForUnknown(): void
    {
        $this->client->request('GET', '/api/v1/schema/unknown-type');

        $this->assertResponseStatusCodeSame(404);
        $data = $this->getJsonResponse();
        $this->assertArrayHasKey('errors', $data);
        $this->assertSame('404', $data['errors'][0]['status']);
        $this->assertSame('Not Found', $data['errors'][0]['title']);
    }

    public function testSchemaEndpointDoesNotRequireAuthentication(): void
    {
        $schema = SchemaFactory::create('public-notes');
        $this->em->persist($schema);
        $this->em->flush();

        // No auth headers
        $this->client->request('GET', '/api/v1/schema/public-notes');

        $this->assertResponseIsSuccessful();
    }

    // ==================== Get Schema by Version Tests ====================

    public function testGetSchemaByVersionSuccessfully(): void
    {
        $schema1 = SchemaFactory::create('versioned', '1.0.0', isDefault: true);
        $schema2 = SchemaFactory::create('versioned', '2.0.0', isDefault: false);
        $this->em->persist($schema1);
        $this->em->persist($schema2);
        $this->em->flush();

        $this->client->request('GET', '/api/v1/schema/versioned/2.0.0');

        $this->assertResponseIsSuccessful();
        $data = $this->getJsonResponse();
        $this->assertSame('2.0.0', $data['data']['attributes']['version']);
    }

    public function testGetSchemaByVersionReturns404ForUnknownType(): void
    {
        $this->client->request('GET', '/api/v1/schema/nonexistent/1.0.0');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetSchemaByVersionReturns404ForUnknownVersion(): void
    {
        $schema = SchemaFactory::create('existing', '1.0.0');
        $this->em->persist($schema);
        $this->em->flush();

        $this->client->request('GET', '/api/v1/schema/existing/9.9.9');

        $this->assertResponseStatusCodeSame(404);
    }

    // ==================== List Object Types Tests ====================

    public function testListObjectTypesReturnsAllTypes(): void
    {
        $schema1 = SchemaFactory::create('notes');
        $schema2 = SchemaFactory::create('articles');
        $schema3 = SchemaFactory::create('tasks');
        $this->em->persist($schema1);
        $this->em->persist($schema2);
        $this->em->persist($schema3);
        $this->em->flush();

        $this->client->request('GET', '/api/v1/schema');

        $this->assertResponseIsSuccessful();
        $data = $this->getJsonResponse();
        $this->assertArrayHasKey('data', $data);
        $this->assertCount(3, $data['data']);

        $types = array_map(fn ($item) => $item['id'], $data['data']);
        $this->assertContains('notes', $types);
        $this->assertContains('articles', $types);
        $this->assertContains('tasks', $types);
    }

    public function testListObjectTypesReturnsEmptyWhenNoSchemas(): void
    {
        $this->client->request('GET', '/api/v1/schema');

        $this->assertResponseIsSuccessful();
        $data = $this->getJsonResponse();
        $this->assertSame([], $data['data']);
    }

    public function testListObjectTypesReturnsUniqueTypes(): void
    {
        // Create multiple versions of same type
        $schema1 = SchemaFactory::create('notes', '1.0.0', isDefault: true);
        $schema2 = SchemaFactory::create('notes', '2.0.0', isDefault: false);
        $this->em->persist($schema1);
        $this->em->persist($schema2);
        $this->em->flush();

        $this->client->request('GET', '/api/v1/schema');

        $this->assertResponseIsSuccessful();
        $data = $this->getJsonResponse();

        // Should have only one 'notes' entry
        $types = array_map(fn ($item) => $item['id'], $data['data']);
        $this->assertCount(1, $types);
        $this->assertSame('notes', $types[0]);
    }

    public function testListObjectTypesReturnsCorrectFormat(): void
    {
        $schema = SchemaFactory::create('items');
        $this->em->persist($schema);
        $this->em->flush();

        $this->client->request('GET', '/api/v1/schema');

        $this->assertResponseIsSuccessful();
        $data = $this->getJsonResponse();

        $this->assertSame('object-types', $data['data'][0]['type']);
        $this->assertSame('items', $data['data'][0]['id']);
        $this->assertSame('items', $data['data'][0]['attributes']['name']);
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
}
