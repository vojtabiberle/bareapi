<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\Service;

use Bareapi\Entity\MetaRef;
use Bareapi\Repository\MetaRefRepositoryInterface;
use Bareapi\Schema\OnDeleteBehavior;
use Bareapi\Schema\RefersToDefinition;
use Bareapi\Service\ReferenceIndexService;
use Bareapi\Service\SchemaServiceInterface;
use Bareapi\Tests\Factory\MetaObjectFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\UuidInterface;

final class ReferenceIndexServiceTest extends TestCase
{
    /**
     * @var MetaRefRepositoryInterface&MockObject
     */
    private MetaRefRepositoryInterface $metaRefRepository;

    /**
     * @var SchemaServiceInterface&MockObject
     */
    private SchemaServiceInterface $schemaService;

    private ReferenceIndexService $service;

    protected function setUp(): void
    {
        $this->metaRefRepository = $this->createMock(MetaRefRepositoryInterface::class);
        $this->schemaService = $this->createMock(SchemaServiceInterface::class);
        $this->service = new ReferenceIndexService($this->metaRefRepository, $this->schemaService);
    }

    public function testSyncRefsWithNoDefinitionsDeletesExisting(): void
    {
        $metaObject = MetaObjectFactory::create();
        $data = ['title' => 'Test'];

        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->with($metaObject->getObjectType())
            ->willReturn([]);

        $this->metaRefRepository->expects($this->once())
            ->method('deleteBySource')
            ->with(
                $metaObject->getProjectId(),
                $metaObject->getObjectType(),
                $metaObject->getUuid()
            );

        $this->metaRefRepository->expects($this->never())
            ->method('syncRefs');

        $this->service->syncRefsForObject($metaObject, $data);
    }

    public function testSyncRefsWithSingleReference(): void
    {
        $metaObject = MetaObjectFactory::create(
            objectType: 'notes',
        );
        $targetUuid = '550e8400-e29b-41d4-a716-446655440000';
        $data = [
            'title' => 'Test Note',
            'author_id' => $targetUuid,
        ];

        $definition = new RefersToDefinition(
            'data.author_id',
            'users',
            'uuid',
            OnDeleteBehavior::Restrict
        );

        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->with('notes')
            ->willReturn([$definition]);

        $capturedRefs = null;
        $this->metaRefRepository->expects($this->once())
            ->method('syncRefs')
            ->willReturnCallback(function ($projectId, $fromType, $fromUuid, $refs) use (&$capturedRefs, $metaObject) {
                $this->assertEquals($metaObject->getProjectId(), $projectId);
                $this->assertEquals('notes', $fromType);
                $this->assertEquals($metaObject->getUuid()->toString(), $fromUuid->toString());
                $capturedRefs = $refs;
            });

        $this->service->syncRefsForObject($metaObject, $data);

        $this->assertCount(1, $capturedRefs);
        $this->assertEquals('data.author_id', $capturedRefs[0]->getPath());
        $this->assertEquals('users', $capturedRefs[0]->getToType());
        $this->assertEquals($targetUuid, $capturedRefs[0]->getToUuid()->toString());
    }

    public function testSyncRefsWithArrayOfReferences(): void
    {
        $metaObject = MetaObjectFactory::create(objectType: 'articles');
        $uuid1 = '550e8400-e29b-41d4-a716-446655440001';
        $uuid2 = '550e8400-e29b-41d4-a716-446655440002';
        $data = [
            'title' => 'Test Article',
            'tag_ids' => [$uuid1, $uuid2],
        ];

        $definition = new RefersToDefinition(
            'data.tag_ids',
            'tags',
            'uuid',
            OnDeleteBehavior::Restrict
        );

        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->with('articles')
            ->willReturn([$definition]);

        $capturedRefs = null;
        $this->metaRefRepository->expects($this->once())
            ->method('syncRefs')
            ->willReturnCallback(function ($projectId, $fromType, $fromUuid, $refs) use (&$capturedRefs) {
                $capturedRefs = $refs;
            });

        $this->service->syncRefsForObject($metaObject, $data);

        $this->assertCount(2, $capturedRefs);
        $this->assertEquals($uuid1, $capturedRefs[0]->getToUuid()->toString());
        $this->assertEquals($uuid2, $capturedRefs[1]->getToUuid()->toString());
    }

    public function testSyncRefsWithNestedPath(): void
    {
        $metaObject = MetaObjectFactory::create(objectType: 'orders');
        $customerUuid = '550e8400-e29b-41d4-a716-446655440000';
        $data = [
            'title' => 'Order',
            'customer' => [
                'id' => $customerUuid,
                'name' => 'Test Customer',
            ],
        ];

        $definition = new RefersToDefinition(
            'data.customer.id',
            'customers',
            'uuid',
            OnDeleteBehavior::Restrict
        );

        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->with('orders')
            ->willReturn([$definition]);

        $capturedRefs = null;
        $this->metaRefRepository->expects($this->once())
            ->method('syncRefs')
            ->willReturnCallback(function ($projectId, $fromType, $fromUuid, $refs) use (&$capturedRefs) {
                $capturedRefs = $refs;
            });

        $this->service->syncRefsForObject($metaObject, $data);

        $this->assertCount(1, $capturedRefs);
        $this->assertEquals($customerUuid, $capturedRefs[0]->getToUuid()->toString());
    }

    public function testSyncRefsWithArrayOfObjects(): void
    {
        $metaObject = MetaObjectFactory::create(objectType: 'invoices');
        $productUuid1 = '550e8400-e29b-41d4-a716-446655440001';
        $productUuid2 = '550e8400-e29b-41d4-a716-446655440002';
        $data = [
            'title' => 'Invoice',
            'items' => [
                ['product_id' => $productUuid1, 'quantity' => 1],
                ['product_id' => $productUuid2, 'quantity' => 2],
            ],
        ];

        $definition = new RefersToDefinition(
            'data.items.product_id',
            'products',
            'uuid',
            OnDeleteBehavior::Restrict
        );

        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->with('invoices')
            ->willReturn([$definition]);

        $capturedRefs = null;
        $this->metaRefRepository->expects($this->once())
            ->method('syncRefs')
            ->willReturnCallback(function ($projectId, $fromType, $fromUuid, $refs) use (&$capturedRefs) {
                $capturedRefs = $refs;
            });

        $this->service->syncRefsForObject($metaObject, $data);

        $this->assertCount(2, $capturedRefs);
        $this->assertEquals($productUuid1, $capturedRefs[0]->getToUuid()->toString());
        $this->assertEquals($productUuid2, $capturedRefs[1]->getToUuid()->toString());
    }

    public function testSyncRefsSkipsEmptyValues(): void
    {
        $metaObject = MetaObjectFactory::create(objectType: 'notes');
        $data = [
            'title' => 'Test',
            'author_id' => '',  // Empty string
        ];

        $definition = new RefersToDefinition(
            'data.author_id',
            'users',
            'uuid',
            OnDeleteBehavior::Restrict
        );

        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->with('notes')
            ->willReturn([$definition]);

        $capturedRefs = null;
        $this->metaRefRepository->expects($this->once())
            ->method('syncRefs')
            ->willReturnCallback(function ($projectId, $fromType, $fromUuid, $refs) use (&$capturedRefs) {
                $capturedRefs = $refs;
            });

        $this->service->syncRefsForObject($metaObject, $data);

        $this->assertCount(0, $capturedRefs);
    }

    public function testSyncRefsSkipsInvalidUuids(): void
    {
        $metaObject = MetaObjectFactory::create(objectType: 'notes');
        $data = [
            'title' => 'Test',
            'author_id' => 'not-a-valid-uuid',
        ];

        $definition = new RefersToDefinition(
            'data.author_id',
            'users',
            'uuid',
            OnDeleteBehavior::Restrict
        );

        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->with('notes')
            ->willReturn([$definition]);

        $capturedRefs = null;
        $this->metaRefRepository->expects($this->once())
            ->method('syncRefs')
            ->willReturnCallback(function ($projectId, $fromType, $fromUuid, $refs) use (&$capturedRefs) {
                $capturedRefs = $refs;
            });

        $this->service->syncRefsForObject($metaObject, $data);

        $this->assertCount(0, $capturedRefs);
    }

    public function testSyncRefsHandlesMissingPath(): void
    {
        $metaObject = MetaObjectFactory::create(objectType: 'notes');
        $data = [
            'title' => 'Test',
            // author_id is missing
        ];

        $definition = new RefersToDefinition(
            'data.author_id',
            'users',
            'uuid',
            OnDeleteBehavior::Restrict
        );

        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->with('notes')
            ->willReturn([$definition]);

        $capturedRefs = null;
        $this->metaRefRepository->expects($this->once())
            ->method('syncRefs')
            ->willReturnCallback(function ($projectId, $fromType, $fromUuid, $refs) use (&$capturedRefs) {
                $capturedRefs = $refs;
            });

        $this->service->syncRefsForObject($metaObject, $data);

        $this->assertCount(0, $capturedRefs);
    }

    public function testSyncRefsWithMultipleDefinitions(): void
    {
        $metaObject = MetaObjectFactory::create(objectType: 'notes');
        $authorUuid = '550e8400-e29b-41d4-a716-446655440001';
        $categoryUuid = '550e8400-e29b-41d4-a716-446655440002';
        $data = [
            'title' => 'Test',
            'author_id' => $authorUuid,
            'category_id' => $categoryUuid,
        ];

        $definitions = [
            new RefersToDefinition(
                'data.author_id',
                'users',
                'uuid',
                OnDeleteBehavior::Restrict
            ),
            new RefersToDefinition(
                'data.category_id',
                'categories',
                'uuid',
                OnDeleteBehavior::Cascade
            ),
        ];

        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->with('notes')
            ->willReturn($definitions);

        $capturedRefs = null;
        $this->metaRefRepository->expects($this->once())
            ->method('syncRefs')
            ->willReturnCallback(function ($projectId, $fromType, $fromUuid, $refs) use (&$capturedRefs) {
                $capturedRefs = $refs;
            });

        $this->service->syncRefsForObject($metaObject, $data);

        $this->assertCount(2, $capturedRefs);

        $paths = array_map(fn (MetaRef $r) => $r->getPath(), $capturedRefs);
        $this->assertContains('data.author_id', $paths);
        $this->assertContains('data.category_id', $paths);
    }

    public function testRemoveRefsForObject(): void
    {
        $metaObject = MetaObjectFactory::create();

        $this->metaRefRepository->expects($this->once())
            ->method('deleteBySource')
            ->with(
                $metaObject->getProjectId(),
                $metaObject->getObjectType(),
                $metaObject->getUuid()
            );

        $this->service->removeRefsForObject($metaObject);
    }

    public function testSyncRefsHandlesMixedValidInvalidUuids(): void
    {
        $metaObject = MetaObjectFactory::create(objectType: 'articles');
        $validUuid = '550e8400-e29b-41d4-a716-446655440001';
        $data = [
            'title' => 'Test',
            'tag_ids' => [$validUuid, 'invalid', '', '550e8400-e29b-41d4-a716-446655440002'],
        ];

        $definition = new RefersToDefinition(
            'data.tag_ids',
            'tags',
            'uuid',
            OnDeleteBehavior::Restrict
        );

        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->willReturn([$definition]);

        $capturedRefs = null;
        $this->metaRefRepository->expects($this->once())
            ->method('syncRefs')
            ->willReturnCallback(function ($projectId, $fromType, $fromUuid, $refs) use (&$capturedRefs) {
                $capturedRefs = $refs;
            });

        $this->service->syncRefsForObject($metaObject, $data);

        // Only valid UUIDs should be included
        $this->assertCount(2, $capturedRefs);
    }

    public function testSyncRefsStripsDataPrefix(): void
    {
        $metaObject = MetaObjectFactory::create(objectType: 'notes');
        $targetUuid = '550e8400-e29b-41d4-a716-446655440000';
        $data = [
            'author_id' => $targetUuid,
        ];

        // Definition has "data." prefix but data doesn't
        $definition = new RefersToDefinition(
            'data.author_id',
            'users',
            'uuid',
            OnDeleteBehavior::Restrict
        );

        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->willReturn([$definition]);

        $capturedRefs = null;
        $this->metaRefRepository->expects($this->once())
            ->method('syncRefs')
            ->willReturnCallback(function ($projectId, $fromType, $fromUuid, $refs) use (&$capturedRefs) {
                $capturedRefs = $refs;
            });

        $this->service->syncRefsForObject($metaObject, $data);

        $this->assertCount(1, $capturedRefs);
        $this->assertEquals($targetUuid, $capturedRefs[0]->getToUuid()->toString());
    }

    public function testSyncRefsPopulatesCorrectMetaRefFields(): void
    {
        $metaObject = MetaObjectFactory::create(
            objectType: 'notes',
            projectId: 456,
        );
        $targetUuid = '550e8400-e29b-41d4-a716-446655440000';
        $data = [
            'author_id' => $targetUuid,
        ];

        $definition = new RefersToDefinition(
            'data.author_id',
            'users',
            'uuid',
            OnDeleteBehavior::Restrict
        );

        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->willReturn([$definition]);

        $capturedRefs = null;
        $this->metaRefRepository->expects($this->once())
            ->method('syncRefs')
            ->willReturnCallback(function ($projectId, $fromType, $fromUuid, $refs) use (&$capturedRefs) {
                $capturedRefs = $refs;
            });

        $this->service->syncRefsForObject($metaObject, $data);

        $this->assertCount(1, $capturedRefs);
        $ref = $capturedRefs[0];

        $this->assertEquals(456, $ref->getProjectId());
        $this->assertEquals('notes', $ref->getFromType());
        $this->assertEquals($metaObject->getUuid()->toString(), $ref->getFromUuid()->toString());
        $this->assertEquals('data.author_id', $ref->getPath());
        $this->assertEquals('users', $ref->getToType());
        $this->assertEquals($targetUuid, $ref->getToUuid()->toString());
    }
}
