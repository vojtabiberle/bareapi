<?php

declare(strict_types=1);

namespace Bareapi\Tests\Feature\MetastoreParity;

use Bareapi\Repository\SchemaRepository;
use Bareapi\Tests\Feature\FeatureTestCase;
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
