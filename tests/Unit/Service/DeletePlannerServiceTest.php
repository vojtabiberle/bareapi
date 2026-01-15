<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\Service;

use Bareapi\Entity\MetaObject;
use Bareapi\Entity\MetaRef;
use Bareapi\Exception\DeleteRestrictedException;
use Bareapi\Repository\MetaObjectRepositoryInterface;
use Bareapi\Repository\MetaRefRepositoryInterface;
use Bareapi\Schema\OnDeleteBehavior;
use Bareapi\Schema\RefersToDefinition;
use Bareapi\Service\DeletePlannerService;
use Bareapi\Service\ReferenceIndexServiceInterface;
use Bareapi\Service\SchemaServiceInterface;
use Bareapi\Tests\Factory\MetaObjectFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class DeletePlannerServiceTest extends TestCase
{
    /**
     * @var MetaObjectRepositoryInterface&MockObject
     */
    private MetaObjectRepositoryInterface $metaObjectRepository;

    /**
     * @var MetaRefRepositoryInterface&MockObject
     */
    private MetaRefRepositoryInterface $metaRefRepository;

    /**
     * @var SchemaServiceInterface&MockObject
     */
    private SchemaServiceInterface $schemaService;

    /**
     * @var ReferenceIndexServiceInterface&MockObject
     */
    private ReferenceIndexServiceInterface $referenceIndexService;

    private DeletePlannerService $service;

    protected function setUp(): void
    {
        $this->metaObjectRepository = $this->createMock(MetaObjectRepositoryInterface::class);
        $this->metaRefRepository = $this->createMock(MetaRefRepositoryInterface::class);
        $this->schemaService = $this->createMock(SchemaServiceInterface::class);
        $this->referenceIndexService = $this->createMock(ReferenceIndexServiceInterface::class);

        $this->service = new DeletePlannerService(
            $this->metaObjectRepository,
            $this->metaRefRepository,
            $this->schemaService,
            $this->referenceIndexService,
        );
    }

    public function testDeleteObjectWithNoReferences(): void
    {
        $target = MetaObjectFactory::create();

        $this->metaRefRepository->expects($this->once())
            ->method('findInboundRefs')
            ->with($target->getProjectId(), $target->getObjectType(), $target->getUuid())
            ->willReturn([]);

        $this->metaObjectRepository->expects($this->once())
            ->method('softDelete')
            ->with($target);

        $this->referenceIndexService->expects($this->once())
            ->method('removeRefsForObject')
            ->with($target);

        $this->service->executeDelete($target);
    }

    public function testDeleteThrowsExceptionWhenRestrictReferencesExist(): void
    {
        $target = MetaObjectFactory::create(
            objectType: 'users',
            name: 'user-1',
        );
        $referrer = MetaObjectFactory::create(
            objectType: 'notes',
            name: 'note-1',
        );

        $inboundRef = new MetaRef(
            $target->getProjectId(),
            $referrer->getObjectType(),
            $referrer->getUuid(),
            'data.author_id',
            $target->getObjectType(),
            $target->getUuid(),
        );

        $this->metaRefRepository->expects($this->once())
            ->method('findInboundRefs')
            ->with($target->getProjectId(), $target->getObjectType(), $target->getUuid())
            ->willReturn([$inboundRef]);

        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->with('notes')
            ->willReturn([
                new RefersToDefinition(
                    'data.author_id',
                    'users',
                    'uuid',
                    OnDeleteBehavior::Restrict
                ),
            ]);

        $this->metaObjectRepository->expects($this->never())
            ->method('softDelete');

        $this->expectException(DeleteRestrictedException::class);
        $this->service->executeDelete($target);
    }

    public function testDeleteWithCascadeDeletesReferrers(): void
    {
        $target = MetaObjectFactory::create(
            objectType: 'users',
            name: 'user-1',
        );
        $referrer = MetaObjectFactory::create(
            objectType: 'notes',
            name: 'note-1',
        );

        $inboundRef = new MetaRef(
            $target->getProjectId(),
            $referrer->getObjectType(),
            $referrer->getUuid(),
            'data.author_id',
            $target->getObjectType(),
            $target->getUuid(),
        );

        // First call is for the target (users), second call is for the referrer (notes)
        $this->metaRefRepository->expects($this->exactly(2))
            ->method('findInboundRefs')
            ->willReturnCallback(function ($projectId, $toType, $toUuid) use ($target, $referrer, $inboundRef) {
                if ($toType === $target->getObjectType()) {
                    return [$inboundRef];
                }

                return []; // No refs to the referrer
            });

        // getRefersToDefinitions is called for the referrer type to determine cascade behavior
        $this->schemaService->expects($this->atLeastOnce())
            ->method('getRefersToDefinitions')
            ->willReturnCallback(function ($objectType) {
                if ($objectType === 'notes') {
                    return [
                        new RefersToDefinition(
                            'data.author_id',
                            'users',
                            'uuid',
                            OnDeleteBehavior::Cascade
                        ),
                    ];
                }

                return [];
            });

        $this->metaObjectRepository->expects($this->once())
            ->method('findByUuid')
            ->with($referrer->getUuid())
            ->willReturn($referrer);

        // Both referrer and target should be soft deleted
        $softDeletedObjects = [];
        $this->metaObjectRepository->expects($this->exactly(2))
            ->method('softDelete')
            ->willReturnCallback(function ($obj) use (&$softDeletedObjects) {
                $softDeletedObjects[] = $obj->getObjectType();
            });

        // Both should have refs removed
        $this->referenceIndexService->expects($this->exactly(2))
            ->method('removeRefsForObject');

        $this->service->executeDelete($target);

        $this->assertContains('notes', $softDeletedObjects);
        $this->assertContains('users', $softDeletedObjects);
    }

    public function testDeleteWithAlreadyDeletedReferrerSkipsIt(): void
    {
        $target = MetaObjectFactory::create(
            objectType: 'users',
            name: 'user-1',
        );
        $referrer = MetaObjectFactory::create(
            objectType: 'notes',
            name: 'note-1',
        );
        $referrer->setDeletedAt(new \DateTimeImmutable());

        $inboundRef = new MetaRef(
            $target->getProjectId(),
            $referrer->getObjectType(),
            $referrer->getUuid(),
            'data.author_id',
            $target->getObjectType(),
            $target->getUuid(),
        );

        $this->metaRefRepository->expects($this->once())
            ->method('findInboundRefs')
            ->willReturn([$inboundRef]);

        // Called twice: once for restrict check, once for cascade processing
        $this->schemaService->expects($this->exactly(2))
            ->method('getRefersToDefinitions')
            ->with('notes')
            ->willReturn([
                new RefersToDefinition(
                    'data.author_id',
                    'users',
                    'uuid',
                    OnDeleteBehavior::Cascade
                ),
            ]);

        $this->metaObjectRepository->expects($this->once())
            ->method('findByUuid')
            ->willReturn($referrer);

        // Only target should be deleted since referrer is already deleted
        $this->metaObjectRepository->expects($this->once())
            ->method('softDelete')
            ->with($target);

        $this->referenceIndexService->expects($this->once())
            ->method('removeRefsForObject')
            ->with($target);

        $this->service->executeDelete($target);
    }

    public function testDeleteWithNullReferrerSkipsIt(): void
    {
        $target = MetaObjectFactory::create(
            objectType: 'users',
            name: 'user-1',
        );
        $referrerUuid = Uuid::uuid7();

        $inboundRef = new MetaRef(
            $target->getProjectId(),
            'notes',
            $referrerUuid,
            'data.author_id',
            $target->getObjectType(),
            $target->getUuid(),
        );

        $this->metaRefRepository->expects($this->once())
            ->method('findInboundRefs')
            ->willReturn([$inboundRef]);

        // Called twice: once for restrict check, once for cascade processing
        $this->schemaService->expects($this->exactly(2))
            ->method('getRefersToDefinitions')
            ->with('notes')
            ->willReturn([
                new RefersToDefinition(
                    'data.author_id',
                    'users',
                    'uuid',
                    OnDeleteBehavior::Cascade
                ),
            ]);

        // Referrer not found in DB
        $this->metaObjectRepository->expects($this->once())
            ->method('findByUuid')
            ->with($referrerUuid)
            ->willReturn(null);

        // Only target should be deleted
        $this->metaObjectRepository->expects($this->once())
            ->method('softDelete')
            ->with($target);

        $this->referenceIndexService->expects($this->once())
            ->method('removeRefsForObject')
            ->with($target);

        $this->service->executeDelete($target);
    }

    public function testDeleteWithMissingSchemaDefaultsToRestrict(): void
    {
        $target = MetaObjectFactory::create(
            objectType: 'users',
            name: 'user-1',
        );
        $referrerUuid = Uuid::uuid7();

        $inboundRef = new MetaRef(
            $target->getProjectId(),
            'notes',
            $referrerUuid,
            'data.author_id',
            $target->getObjectType(),
            $target->getUuid(),
        );

        $this->metaRefRepository->expects($this->once())
            ->method('findInboundRefs')
            ->willReturn([$inboundRef]);

        // Schema throws exception - should default to restrict
        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->willThrowException(new \Exception('Schema not found'));

        $this->expectException(DeleteRestrictedException::class);
        $this->service->executeDelete($target);
    }

    public function testDeleteWithUnmatchedPathDefaultsToRestrict(): void
    {
        $target = MetaObjectFactory::create(
            objectType: 'users',
            name: 'user-1',
        );
        $referrerUuid = Uuid::uuid7();

        $inboundRef = new MetaRef(
            $target->getProjectId(),
            'notes',
            $referrerUuid,
            'data.author_id',
            $target->getObjectType(),
            $target->getUuid(),
        );

        $this->metaRefRepository->expects($this->once())
            ->method('findInboundRefs')
            ->willReturn([$inboundRef]);

        // Schema returns definitions but none match the path
        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->with('notes')
            ->willReturn([
                new RefersToDefinition(
                    'data.different_field',  // Different path
                    'users',
                    'uuid',
                    OnDeleteBehavior::Cascade
                ),
            ]);

        $this->expectException(DeleteRestrictedException::class);
        $this->service->executeDelete($target);
    }

    public function testDeleteRestrictedExceptionContainsViolationDetails(): void
    {
        $target = MetaObjectFactory::create(
            objectType: 'users',
            name: 'user-1',
        );
        $referrer1Uuid = Uuid::uuid7();
        $referrer2Uuid = Uuid::uuid7();

        $inboundRefs = [
            new MetaRef(
                $target->getProjectId(),
                'notes',
                $referrer1Uuid,
                'data.author_id',
                $target->getObjectType(),
                $target->getUuid(),
            ),
            new MetaRef(
                $target->getProjectId(),
                'notes',
                $referrer2Uuid,
                'data.author_id',
                $target->getObjectType(),
                $target->getUuid(),
            ),
        ];

        $this->metaRefRepository->expects($this->once())
            ->method('findInboundRefs')
            ->willReturn($inboundRefs);

        $this->schemaService->expects($this->once())
            ->method('getRefersToDefinitions')
            ->with('notes')
            ->willReturn([
                new RefersToDefinition(
                    'data.author_id',
                    'users',
                    'uuid',
                    OnDeleteBehavior::Restrict
                ),
            ]);

        try {
            $this->service->executeDelete($target);
            $this->fail('Expected DeleteRestrictedException');
        } catch (DeleteRestrictedException $e) {
            $violations = $e->getViolations();
            $this->assertCount(1, $violations);
            $this->assertEquals('notes', $violations[0]['fromType']);
            $this->assertEquals('data.author_id', $violations[0]['path']);
            $this->assertEquals(2, $violations[0]['count']);
            $this->assertCount(2, $violations[0]['sample']);
        }
    }

    public function testDeleteWithMultiplePathsGroupsCorrectly(): void
    {
        $target = MetaObjectFactory::create(
            objectType: 'users',
            name: 'user-1',
        );

        $inboundRefs = [
            new MetaRef(
                $target->getProjectId(),
                'notes',
                Uuid::uuid7(),
                'data.author_id',
                $target->getObjectType(),
                $target->getUuid(),
            ),
            new MetaRef(
                $target->getProjectId(),
                'notes',
                Uuid::uuid7(),
                'data.reviewer_id',
                $target->getObjectType(),
                $target->getUuid(),
            ),
        ];

        $this->metaRefRepository->expects($this->once())
            ->method('findInboundRefs')
            ->willReturn($inboundRefs);

        // Both paths configured as restrict
        $this->schemaService->expects($this->exactly(2))
            ->method('getRefersToDefinitions')
            ->with('notes')
            ->willReturn([
                new RefersToDefinition('data.author_id', 'users', 'uuid', OnDeleteBehavior::Restrict),
                new RefersToDefinition('data.reviewer_id', 'users', 'uuid', OnDeleteBehavior::Restrict),
            ]);

        try {
            $this->service->executeDelete($target);
            $this->fail('Expected DeleteRestrictedException');
        } catch (DeleteRestrictedException $e) {
            $violations = $e->getViolations();
            $this->assertCount(2, $violations);
        }
    }
}
