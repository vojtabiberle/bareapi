<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\Service;

use Bareapi\Entity\MetaObject;
use Bareapi\Repository\MetaObjectRepositoryInterface;
use Bareapi\Schema\OnDeleteBehavior;
use Bareapi\Schema\RefersToDefinition;
use Bareapi\Service\RelationshipEnrichmentService;
use Bareapi\Service\SchemaServiceInterface;
use Bareapi\Tests\Factory\MetaObjectFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RelationshipEnrichmentServiceTest extends TestCase
{
    /**
     * @var MetaObjectRepositoryInterface&MockObject
     */
    private MetaObjectRepositoryInterface $metaObjectRepository;

    /**
     * @var SchemaServiceInterface&MockObject
     */
    private SchemaServiceInterface $schemaService;

    private RelationshipEnrichmentService $service;

    protected function setUp(): void
    {
        $this->metaObjectRepository = $this->createMock(MetaObjectRepositoryInterface::class);
        $this->schemaService = $this->createMock(SchemaServiceInterface::class);
        $this->service = new RelationshipEnrichmentService(
            $this->metaObjectRepository,
            $this->schemaService,
        );
    }

    public function testEnrichRelationshipsReturnsEmptyForNoDefinitions(): void
    {
        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->with('notes')
            ->willReturn([]);

        $result = $this->service->enrichRelationships(
            ['title' => 'Test'],
            'notes',
            [],
            123,
            'org-1',
            'https://api.example.com'
        );

        $this->assertSame([], $result);
    }

    public function testEnrichRelationshipsWithSingleReference(): void
    {
        $targetUuid = '550e8400-e29b-41d4-a716-446655440000';
        $data = ['author_id' => $targetUuid];

        $targetObject = MetaObjectFactory::create(
            objectType: 'users',
            name: 'user-1',
            organizationId: 'org-1',
            projectId: 123,
        );
        $targetObject->setUuid(\Ramsey\Uuid\Uuid::fromString($targetUuid));

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

        $this->metaObjectRepository->expects($this->once())
            ->method('findByUuidString')
            ->with($targetUuid)
            ->willReturn($targetObject);

        $result = $this->service->enrichRelationships(
            $data,
            'notes',
            [],
            123,
            'org-1',
            'https://api.example.com'
        );

        $this->assertArrayHasKey('data.author_id', $result);
        $this->assertEquals('https://api.example.com/users/' . $targetUuid, $result['data.author_id']['url']);
        $this->assertEquals('data.author_id', $result['data.author_id']['meta']['sourcePath']);
        $this->assertNotNull($result['data.author_id']['data']);
    }

    public function testEnrichRelationshipsWithArrayOfReferences(): void
    {
        $uuid1 = '550e8400-e29b-41d4-a716-446655440001';
        $uuid2 = '550e8400-e29b-41d4-a716-446655440002';
        $data = ['tag_ids' => [$uuid1, $uuid2]];

        $tag1 = MetaObjectFactory::create(objectType: 'tags', name: 'tag-1', organizationId: 'org-1', projectId: 123);
        $tag1->setUuid(\Ramsey\Uuid\Uuid::fromString($uuid1));

        $tag2 = MetaObjectFactory::create(objectType: 'tags', name: 'tag-2', organizationId: 'org-1', projectId: 123);
        $tag2->setUuid(\Ramsey\Uuid\Uuid::fromString($uuid2));

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

        $this->metaObjectRepository->expects($this->exactly(2))
            ->method('findByUuidString')
            ->willReturnCallback(function ($uuid) use ($uuid1, $uuid2, $tag1, $tag2) {
                return match ($uuid) {
                    $uuid1 => $tag1,
                    $uuid2 => $tag2,
                    default => null,
                };
            });

        $result = $this->service->enrichRelationships(
            $data,
            'articles',
            [],
            123,
            'org-1',
            'https://api.example.com'
        );

        $this->assertArrayHasKey('data.tag_ids', $result);
        $this->assertIsArray($result['data.tag_ids']['data']);
        $this->assertCount(2, $result['data.tag_ids']['data']);
        $this->assertIsArray($result['data.tag_ids']['url']);
        $this->assertCount(2, $result['data.tag_ids']['url']);
    }

    public function testEnrichRelationshipsFiltersByRequestedPaths(): void
    {
        $authorUuid = '550e8400-e29b-41d4-a716-446655440001';
        $categoryUuid = '550e8400-e29b-41d4-a716-446655440002';
        $data = [
            'author_id' => $authorUuid,
            'category_id' => $categoryUuid,
        ];

        $author = MetaObjectFactory::create(objectType: 'users', name: 'user-1', organizationId: 'org-1', projectId: 123);
        $author->setUuid(\Ramsey\Uuid\Uuid::fromString($authorUuid));

        $definitions = [
            new RefersToDefinition('data.author_id', 'users', 'uuid', OnDeleteBehavior::Restrict),
            new RefersToDefinition('data.category_id', 'categories', 'uuid', OnDeleteBehavior::Restrict),
        ];

        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->willReturn($definitions);

        $this->metaObjectRepository->expects($this->once())
            ->method('findByUuidString')
            ->with($authorUuid)
            ->willReturn($author);

        // Only request author_id
        $result = $this->service->enrichRelationships(
            $data,
            'notes',
            ['author_id'],  // Only include author_id
            123,
            'org-1',
            'https://api.example.com'
        );

        $this->assertArrayHasKey('data.author_id', $result);
        $this->assertArrayNotHasKey('data.category_id', $result);
    }

    public function testEnrichRelationshipsReturnsNullDataForMissingObject(): void
    {
        $targetUuid = '550e8400-e29b-41d4-a716-446655440000';
        $data = ['author_id' => $targetUuid];

        $definition = new RefersToDefinition(
            'data.author_id',
            'users',
            'uuid',
            OnDeleteBehavior::Restrict
        );

        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->willReturn([$definition]);

        $this->metaObjectRepository->expects($this->once())
            ->method('findByUuidString')
            ->with($targetUuid)
            ->willReturn(null);

        $result = $this->service->enrichRelationships(
            $data,
            'notes',
            [],
            123,
            'org-1',
            'https://api.example.com'
        );

        $this->assertArrayHasKey('data.author_id', $result);
        $this->assertNull($result['data.author_id']['data']);
        $this->assertEquals('https://api.example.com/users/' . $targetUuid, $result['data.author_id']['url']);
    }

    public function testEnrichRelationshipsSkipsDeletedObjects(): void
    {
        $targetUuid = '550e8400-e29b-41d4-a716-446655440000';
        $data = ['author_id' => $targetUuid];

        $deletedObject = MetaObjectFactory::create(
            objectType: 'users',
            organizationId: 'org-1',
            projectId: 123,
        );
        $deletedObject->setUuid(\Ramsey\Uuid\Uuid::fromString($targetUuid));
        $deletedObject->setDeletedAt(new \DateTimeImmutable());

        $definition = new RefersToDefinition(
            'data.author_id',
            'users',
            'uuid',
            OnDeleteBehavior::Restrict
        );

        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->willReturn([$definition]);

        $this->metaObjectRepository->expects($this->once())
            ->method('findByUuidString')
            ->willReturn($deletedObject);

        $result = $this->service->enrichRelationships(
            $data,
            'notes',
            [],
            123,
            'org-1',
            'https://api.example.com'
        );

        $this->assertArrayHasKey('data.author_id', $result);
        $this->assertNull($result['data.author_id']['data']);
    }

    public function testEnrichRelationshipsSkipsWrongObjectType(): void
    {
        $targetUuid = '550e8400-e29b-41d4-a716-446655440000';
        $data = ['author_id' => $targetUuid];

        $wrongTypeObject = MetaObjectFactory::create(
            objectType: 'categories',  // Expected 'users'
            organizationId: 'org-1',
            projectId: 123,
        );
        $wrongTypeObject->setUuid(\Ramsey\Uuid\Uuid::fromString($targetUuid));

        $definition = new RefersToDefinition(
            'data.author_id',
            'users',
            'uuid',
            OnDeleteBehavior::Restrict
        );

        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->willReturn([$definition]);

        $this->metaObjectRepository->expects($this->once())
            ->method('findByUuidString')
            ->willReturn($wrongTypeObject);

        $result = $this->service->enrichRelationships(
            $data,
            'notes',
            [],
            123,
            'org-1',
            'https://api.example.com'
        );

        $this->assertNull($result['data.author_id']['data']);
    }

    public function testEnrichRelationshipsSkipsWrongOrganization(): void
    {
        $targetUuid = '550e8400-e29b-41d4-a716-446655440000';
        $data = ['author_id' => $targetUuid];

        $wrongOrgObject = MetaObjectFactory::create(
            objectType: 'users',
            organizationId: 'org-2',  // Expected 'org-1'
            projectId: 123,
        );
        $wrongOrgObject->setUuid(\Ramsey\Uuid\Uuid::fromString($targetUuid));

        $definition = new RefersToDefinition(
            'data.author_id',
            'users',
            'uuid',
            OnDeleteBehavior::Restrict
        );

        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->willReturn([$definition]);

        $this->metaObjectRepository->expects($this->once())
            ->method('findByUuidString')
            ->willReturn($wrongOrgObject);

        $result = $this->service->enrichRelationships(
            $data,
            'notes',
            [],
            123,
            'org-1',
            'https://api.example.com'
        );

        $this->assertNull($result['data.author_id']['data']);
    }

    public function testEnrichRelationshipsSkipsWrongProject(): void
    {
        $targetUuid = '550e8400-e29b-41d4-a716-446655440000';
        $data = ['author_id' => $targetUuid];

        $wrongProjectObject = MetaObjectFactory::create(
            objectType: 'users',
            organizationId: 'org-1',
            projectId: 999,  // Expected 123
        );
        $wrongProjectObject->setUuid(\Ramsey\Uuid\Uuid::fromString($targetUuid));

        $definition = new RefersToDefinition(
            'data.author_id',
            'users',
            'uuid',
            OnDeleteBehavior::Restrict
        );

        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->willReturn([$definition]);

        $this->metaObjectRepository->expects($this->once())
            ->method('findByUuidString')
            ->willReturn($wrongProjectObject);

        $result = $this->service->enrichRelationships(
            $data,
            'notes',
            [],
            123,
            'org-1',
            'https://api.example.com'
        );

        $this->assertNull($result['data.author_id']['data']);
    }

    public function testEnrichRelationshipsAllowsNullProjectScope(): void
    {
        $targetUuid = '550e8400-e29b-41d4-a716-446655440000';
        $data = ['author_id' => $targetUuid];

        $targetObject = MetaObjectFactory::create(
            objectType: 'users',
            organizationId: 'org-1',
            projectId: 456,  // Object has a project
        );
        $targetObject->setUuid(\Ramsey\Uuid\Uuid::fromString($targetUuid));

        $definition = new RefersToDefinition(
            'data.author_id',
            'users',
            'uuid',
            OnDeleteBehavior::Restrict
        );

        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->willReturn([$definition]);

        $this->metaObjectRepository->expects($this->once())
            ->method('findByUuidString')
            ->willReturn($targetObject);

        // Request with null projectId - should match any project
        $result = $this->service->enrichRelationships(
            $data,
            'notes',
            [],
            null,  // Null project scope
            'org-1',
            'https://api.example.com'
        );

        $this->assertNotNull($result['data.author_id']['data']);
    }

    public function testEnrichRelationshipsSkipsEmptyPaths(): void
    {
        $data = ['author_id' => ''];  // Empty value

        $definition = new RefersToDefinition(
            'data.author_id',
            'users',
            'uuid',
            OnDeleteBehavior::Restrict
        );

        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->willReturn([$definition]);

        $this->metaObjectRepository->expects($this->never())
            ->method('findByUuidString');

        $result = $this->service->enrichRelationships(
            $data,
            'notes',
            [],
            123,
            'org-1',
            'https://api.example.com'
        );

        $this->assertEmpty($result);
    }

    public function testEnrichRelationshipsWithInvalidUuidReturnsNullData(): void
    {
        $data = ['author_id' => 'not-a-valid-uuid'];

        $definition = new RefersToDefinition(
            'data.author_id',
            'users',
            'uuid',
            OnDeleteBehavior::Restrict
        );

        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->willReturn([$definition]);

        // Invalid UUID won't be looked up in repository
        $this->metaObjectRepository->expects($this->never())
            ->method('findByUuidString');

        $result = $this->service->enrichRelationships(
            $data,
            'notes',
            [],
            123,
            'org-1',
            'https://api.example.com'
        );

        // Relationship entry still exists but with null data
        $this->assertArrayHasKey('data.author_id', $result);
        $this->assertNull($result['data.author_id']['data']);
    }

    public function testEnrichRelationshipsWithNestedPath(): void
    {
        $customerUuid = '550e8400-e29b-41d4-a716-446655440000';
        $data = [
            'customer' => [
                'id' => $customerUuid,
            ],
        ];

        $customer = MetaObjectFactory::create(
            objectType: 'customers',
            organizationId: 'org-1',
            projectId: 123,
        );
        $customer->setUuid(\Ramsey\Uuid\Uuid::fromString($customerUuid));

        $definition = new RefersToDefinition(
            'data.customer.id',
            'customers',
            'uuid',
            OnDeleteBehavior::Restrict
        );

        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->willReturn([$definition]);

        $this->metaObjectRepository->expects($this->once())
            ->method('findByUuidString')
            ->with($customerUuid)
            ->willReturn($customer);

        $result = $this->service->enrichRelationships(
            $data,
            'orders',
            [],
            123,
            'org-1',
            'https://api.example.com'
        );

        $this->assertArrayHasKey('data.customer.id', $result);
        $this->assertNotNull($result['data.customer.id']['data']);
    }

    public function testEnrichRelationshipsWithArrayOfObjects(): void
    {
        $productUuid1 = '550e8400-e29b-41d4-a716-446655440001';
        $productUuid2 = '550e8400-e29b-41d4-a716-446655440002';
        $data = [
            'items' => [
                ['product_id' => $productUuid1],
                ['product_id' => $productUuid2],
            ],
        ];

        $product1 = MetaObjectFactory::create(objectType: 'products', organizationId: 'org-1', projectId: 123);
        $product1->setUuid(\Ramsey\Uuid\Uuid::fromString($productUuid1));

        $product2 = MetaObjectFactory::create(objectType: 'products', organizationId: 'org-1', projectId: 123);
        $product2->setUuid(\Ramsey\Uuid\Uuid::fromString($productUuid2));

        $definition = new RefersToDefinition(
            'data.items.product_id',
            'products',
            'uuid',
            OnDeleteBehavior::Restrict
        );

        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->willReturn([$definition]);

        $this->metaObjectRepository->expects($this->exactly(2))
            ->method('findByUuidString')
            ->willReturnCallback(function ($uuid) use ($productUuid1, $productUuid2, $product1, $product2) {
                return match ($uuid) {
                    $productUuid1 => $product1,
                    $productUuid2 => $product2,
                    default => null,
                };
            });

        $result = $this->service->enrichRelationships(
            $data,
            'invoices',
            [],
            123,
            'org-1',
            'https://api.example.com'
        );

        $this->assertArrayHasKey('data.items.product_id', $result);
        $this->assertCount(2, $result['data.items.product_id']['data']);
    }

    public function testEnrichRelationshipsMatchesPathWithDataPrefix(): void
    {
        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->willReturn([
                new RefersToDefinition('data.author_id', 'users', 'uuid', OnDeleteBehavior::Restrict),
            ]);

        // Request with 'data.' prefix should match
        $result = $this->service->enrichRelationships(
            ['author_id' => '550e8400-e29b-41d4-a716-446655440000'],
            'notes',
            ['data.author_id'],
            123,
            'org-1',
            'https://api.example.com'
        );

        // The path should be included (not filtered out)
        $this->assertArrayHasKey('data.author_id', $result);
    }

    public function testEnrichRelationshipsMatchesPathWithoutDataPrefix(): void
    {
        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->willReturn([
                new RefersToDefinition('data.author_id', 'users', 'uuid', OnDeleteBehavior::Restrict),
            ]);

        // Request without 'data.' prefix should also match
        $result = $this->service->enrichRelationships(
            ['author_id' => '550e8400-e29b-41d4-a716-446655440000'],
            'notes',
            ['author_id'],  // Without data. prefix
            123,
            'org-1',
            'https://api.example.com'
        );

        $this->assertArrayHasKey('data.author_id', $result);
    }

    public function testEnrichRelationshipsDeduplicatesUuidsInArray(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440001';
        $data = ['tag_ids' => [$uuid, $uuid, $uuid]];  // Same UUID repeated

        $tag = MetaObjectFactory::create(objectType: 'tags', organizationId: 'org-1', projectId: 123);
        $tag->setUuid(\Ramsey\Uuid\Uuid::fromString($uuid));

        $definition = new RefersToDefinition(
            'data.tag_ids',
            'tags',
            'uuid',
            OnDeleteBehavior::Restrict
        );

        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->willReturn([$definition]);

        // Should only be called once due to deduplication
        $this->metaObjectRepository->expects($this->once())
            ->method('findByUuidString')
            ->with($uuid)
            ->willReturn($tag);

        $result = $this->service->enrichRelationships(
            $data,
            'articles',
            [],
            123,
            'org-1',
            'https://api.example.com'
        );

        $this->assertCount(1, $result['data.tag_ids']['data']);
    }
}
