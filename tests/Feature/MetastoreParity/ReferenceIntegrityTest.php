<?php

declare(strict_types=1);

namespace Bareapi\Tests\Feature\MetastoreParity;

use Bareapi\Repository\SchemaRepository;
use Bareapi\Tests\Feature\FeatureTestCase;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

final class ReferenceIntegrityTest extends FeatureTestCase
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
            'properties' => [],
        ]);
    }

    public function testCreatesObjectWithExistingReferenceAndRejectsMissingReference(): void
    {
        $this->saveTagBindingSchema('restrict');
        $tagId = $this->createTag('Referenced tag');

        $this->createTagBinding($tagId, 'note-1');
        $this->assertResponseStatusCodeSame(201);

        $this->createTagBinding('018ff3ae-c558-7ed8-8f68-0242ac120099', 'note-2');
        $this->assertResponseStatusCodeSame(422);
    }

    public function testFailedCreateDoesNotLeaveOrphanReferences(): void
    {
        $this->saveTagBindingSchema('restrict');
        $tagId = $this->createTag('Referenced tag');

        $this->createTagBinding($tagId, 'duplicate-name');
        $this->assertResponseStatusCodeSame(201);
        $this->assertSame(1, $this->referenceCount());

        $this->createTagBinding($tagId, 'duplicate-name');
        $this->assertNotSame(201, $this->client->getResponse()->getStatusCode());
        $this->assertSame(1, $this->referenceCount());
    }

    public function testRestrictReferenceBlocksTargetDelete(): void
    {
        $this->saveTagBindingSchema('restrict');
        $tagId = $this->createTag('Protected tag');
        $this->createTagBinding($tagId, 'note-1');
        $this->assertResponseStatusCodeSame(201);

        $this->client->request('DELETE', '/api/v1/repository/tags/' . $tagId);
        $this->assertResponseStatusCodeSame(409);
    }

    public function testCascadeReferenceDeletesReferencingObjects(): void
    {
        $this->saveTagBindingSchema('cascade');
        $tagId = $this->createTag('Cascaded tag');
        $bindingId = $this->createTagBinding($tagId, 'note-1');
        $this->assertResponseStatusCodeSame(201);

        $this->client->request('DELETE', '/api/v1/repository/tags/' . $tagId);
        $this->assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/v1/repository/tag_bindings/' . $bindingId);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testUpdateReplacesReferenceIndex(): void
    {
        $this->saveTagBindingSchema('restrict');
        $oldTagId = $this->createTag('Old tag');
        $newTagId = $this->createTag('New tag');
        $bindingId = $this->createTagBinding($oldTagId, 'note-1');
        $this->assertResponseStatusCodeSame(201);

        $this->client->request(
            'PUT',
            '/api/v1/repository/tag_bindings/' . $bindingId,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'name' => 'note-1',
                'data' => [
                    'tagId' => $newTagId,
                    'objectId' => 'note-1',
                ],
            ], JSON_THROW_ON_ERROR)
        );
        $this->assertResponseStatusCodeSame(200);

        $this->client->request('DELETE', '/api/v1/repository/tags/' . $oldTagId);
        $this->assertResponseStatusCodeSame(204);

        $this->client->request('DELETE', '/api/v1/repository/tags/' . $newTagId);
        $this->assertResponseStatusCodeSame(409);
    }

    public function testCascadeDeleteTraversesReferenceChain(): void
    {
        $this->saveTagBindingSchema('cascade');
        $this->saveNoteReferenceSchema('cascade', 'tag_bindings');
        $tagId = $this->createTag('Root tag');
        $bindingId = $this->createTagBinding($tagId, 'note-1');
        $this->assertResponseStatusCodeSame(201);
        $noteId = $this->createNote('Cascaded note', $bindingId);

        $this->client->request('DELETE', '/api/v1/repository/tags/' . $tagId);
        $this->assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/v1/repository/tag_bindings/' . $bindingId);
        $this->assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/api/v1/repository/notes/' . $noteId);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testRestrictReferenceBlocksDeleteWithoutDeletingCascadeCandidates(): void
    {
        $this->saveTagBindingSchema('cascade');
        $this->saveNoteReferenceSchema('restrict', 'tags');
        $tagId = $this->createTag('Mixed reference tag');
        $bindingId = $this->createTagBinding($tagId, 'note-1');
        $this->assertResponseStatusCodeSame(201);
        $noteId = $this->createNote('Restricting note', $tagId);

        $this->client->request('DELETE', '/api/v1/repository/tags/' . $tagId);
        $this->assertResponseStatusCodeSame(409);

        $this->client->request('GET', '/api/v1/repository/tag_bindings/' . $bindingId);
        $this->assertResponseStatusCodeSame(200);
        $this->client->request('GET', '/api/v1/repository/notes/' . $noteId);
        $this->assertResponseStatusCodeSame(200);
    }

    public function testCreatesReferenceToLegacyTypedTargetByNeutralObjectType(): void
    {
        $this->saveTagBindingSchema('restrict');
        $tagId = $this->insertLegacyTypedTag('Legacy referenced tag');

        $this->createTagBinding($tagId, 'legacy-note-1');

        $this->assertResponseStatusCodeSame(201);
    }

    public function testRestrictReferenceBlocksLegacyTypedTargetDelete(): void
    {
        $this->saveTagBindingSchema('restrict');
        $tagId = $this->insertLegacyTypedTag('Legacy protected tag');
        $this->createTagBinding($tagId, 'legacy-note-2');
        $this->assertResponseStatusCodeSame(201);

        $this->client->request('DELETE', '/api/v1/repository/tags/' . $tagId);

        $this->assertResponseStatusCodeSame(409);
    }

    public function testCascadeDeleteRemovesReferencesForLegacyTypedDependent(): void
    {
        $this->saveTagBindingSchema('cascade');
        $tagId = $this->createTag('Cascade root tag');
        $bindingId = $this->insertLegacyTypedTagBinding($tagId, 'legacy-note-3');

        $this->client->request('DELETE', '/api/v1/repository/tags/' . $tagId);

        $this->assertResponseStatusCodeSame(204);
        $this->client->request('GET', '/api/v1/repository/tag_bindings/' . $bindingId);
        $this->assertResponseStatusCodeSame(404);
        $this->assertSame(0, $this->referenceCount());
    }

    private function saveTagBindingSchema(string $onDelete): void
    {
        $repository = self::getContainer()->get(SchemaRepository::class);
        $this->assertInstanceOf(SchemaRepository::class, $repository);
        $repository->save('tag_bindings', '1.0.0', true, [
            'title' => 'tag_bindings',
            'type' => 'object',
            'properties' => [
                'tagId' => [
                    'type' => 'string',
                ],
                'objectId' => [
                    'type' => 'string',
                ],
            ],
            'x-bareapi.references' => [
                [
                    'property' => 'tagId',
                    'refersTo' => 'tags',
                    'onDelete' => $onDelete,
                ],
            ],
        ]);
    }

    private function saveNoteReferenceSchema(string $onDelete, string $refersTo): void
    {
        $repository = self::getContainer()->get(SchemaRepository::class);
        $this->assertInstanceOf(SchemaRepository::class, $repository);
        $repository->save('notes', '1.0.0', true, [
            'title' => 'notes',
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type' => 'string',
                ],
                'content' => [
                    'type' => 'string',
                ],
                'tagId' => [
                    'type' => 'string',
                ],
            ],
            'x-bareapi.references' => [
                [
                    'property' => 'tagId',
                    'refersTo' => $refersTo,
                    'onDelete' => $onDelete,
                ],
            ],
        ]);
    }

    private function createTag(string $name): string
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
                    'color' => 'red',
                    'creator' => [
                        'id' => '018ff3ae-c558-7ed8-8f68-0242ac120003',
                        'name' => 'Jan',
                    ],
                ],
            ], JSON_THROW_ON_ERROR)
        );
        $this->assertResponseStatusCodeSame(201);

        return $this->responseId();
    }

    private function insertLegacyTypedTag(string $name): string
    {
        $data = [
            'id' => '018ff3ae-c558-7ed8-8f68-0242ac1200d1',
            'name' => $name,
            'color' => 'red',
            'creator' => [
                'id' => '018ff3ae-c558-7ed8-8f68-0242ac1200d2',
                'name' => 'Jan',
            ],
        ];

        return $this->insertLegacyObject('legacy_tags', 'tags', $name, $data);
    }

    private function insertLegacyTypedTagBinding(string $tagId, string $objectId): string
    {
        $data = [
            'tagId' => $tagId,
            'objectId' => $objectId,
        ];
        $bindingId = $this->insertLegacyObject('legacy_tag_bindings', 'tag_bindings', $objectId, $data);

        $connection = self::getContainer()->get(Connection::class);
        $this->assertInstanceOf(Connection::class, $connection);
        $connection->insert('meta_refs', [
            'from_type' => 'tag_bindings',
            'from_uuid' => $bindingId,
            'path' => 'tagId',
            'to_type' => 'tags',
            'to_uuid' => $tagId,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        return $bindingId;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function insertLegacyObject(string $legacyType, string $objectType, string $name, array $data): string
    {
        $connection = self::getContainer()->get(Connection::class);
        $this->assertInstanceOf(Connection::class, $connection);
        $id = match ($objectType . ':' . $name) {
            'tags:Legacy referenced tag' => '018ff3ae-c558-7ed8-8f68-0242ac1200d3',
            'tags:Legacy protected tag' => '018ff3ae-c558-7ed8-8f68-0242ac1200d4',
            default => '018ff3ae-c558-7ed8-8f68-0242ac1200d5',
        };
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $connection->insert('meta_objects', [
            'id' => $id,
            'type' => $legacyType,
            'object_type' => $objectType,
            'schema_version' => '1.0.0',
            'branch' => 'main',
            'name' => $name,
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

    private function createTagBinding(string $tagId, string $objectId): string
    {
        $this->client->request(
            'POST',
            '/api/v1/repository/tag_bindings',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode([
                'name' => $objectId,
                'data' => [
                    'tagId' => $tagId,
                    'objectId' => $objectId,
                ],
            ], JSON_THROW_ON_ERROR)
        );

        if ($this->client->getResponse()->getStatusCode() !== 201) {
            return '';
        }

        return $this->responseId();
    }

    private function createNote(string $title, string $tagId): string
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
                'name' => $title,
                'data' => [
                    'title' => $title,
                    'content' => 'Reference content',
                    'tagId' => $tagId,
                ],
            ], JSON_THROW_ON_ERROR)
        );
        $this->assertResponseStatusCodeSame(201);

        return $this->responseId();
    }

    private function responseId(): string
    {
        $content = $this->client->getResponse()->getContent();
        $response = json_decode(is_string($content) ? $content : '', true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('data', $response);
        $this->assertIsArray($response['data']);
        $this->assertArrayHasKey('id', $response['data']);
        $this->assertIsString($response['data']['id']);

        return $response['data']['id'];
    }

    private function referenceCount(): int
    {
        $connection = self::getContainer()->get(Connection::class);
        $this->assertInstanceOf(Connection::class, $connection);
        $count = $connection->fetchOne('SELECT COUNT(*) FROM meta_refs');

        return is_numeric($count) ? (int) $count : 0;
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
