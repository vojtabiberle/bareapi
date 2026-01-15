<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\Validation;

use Bareapi\Exception\ReferenceValidationException;
use Bareapi\Repository\MetaObjectRepositoryInterface;
use Bareapi\Schema\OnDeleteBehavior;
use Bareapi\Schema\RefersToDefinition;
use Bareapi\Service\SchemaServiceInterface;
use Bareapi\Validation\ReferenceValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ReferenceValidatorTest extends TestCase
{
    private ReferenceValidator $validator;

    private MetaObjectRepositoryInterface&MockObject $metaObjectRepository;

    private SchemaServiceInterface&MockObject $schemaService;

    protected function setUp(): void
    {
        $this->metaObjectRepository = $this->createMock(MetaObjectRepositoryInterface::class);
        $this->schemaService = $this->createMock(SchemaServiceInterface::class);
        $this->validator = new ReferenceValidator(
            $this->metaObjectRepository,
            $this->schemaService
        );
    }

    public function testValidationPassesWithNoRefersToDefinitions(): void
    {
        $this->schemaService->method('getRefersToDefinitions')->willReturn([]);

        $this->validator->validate(
            [
                'tagId' => 'some-uuid',
            ],
            'tag-binding',
            1,
            'org-123'
        );

        // No exception means validation passed
        $this->assertTrue(true);
    }

    public function testValidationPassesWithValidReference(): void
    {
        $definition = new RefersToDefinition(
            path: 'data.tagId',
            targetType: 'tag',
            targetField: 'uuid',
            onDelete: OnDeleteBehavior::Restrict
        );

        $this->schemaService->method('getRefersToDefinitions')->willReturn([$definition]);

        $targetUuid = '01234567-89ab-cdef-0123-456789abcdef';

        $this->metaObjectRepository->method('checkUuidsExist')
            ->with([$targetUuid], 'tag', 1, 'org-123')
            ->willReturn([
                $targetUuid => true,
            ]);

        $this->validator->validate(
            [
                'tagId' => $targetUuid,
            ],
            'tag-binding',
            1,
            'org-123'
        );

        // No exception means validation passed
        $this->assertTrue(true);
    }

    public function testValidationFailsWithMissingReference(): void
    {
        $definition = new RefersToDefinition(
            path: 'data.tagId',
            targetType: 'tag',
            targetField: 'uuid',
            onDelete: OnDeleteBehavior::Restrict
        );

        $this->schemaService->method('getRefersToDefinitions')->willReturn([$definition]);

        $targetUuid = '01234567-89ab-cdef-0123-456789abcdef';

        $this->metaObjectRepository->method('checkUuidsExist')
            ->with([$targetUuid], 'tag', 1, 'org-123')
            ->willReturn([
                $targetUuid => false,
            ]);

        $this->expectException(ReferenceValidationException::class);

        $this->validator->validate(
            [
                'tagId' => $targetUuid,
            ],
            'tag-binding',
            1,
            'org-123'
        );
    }

    public function testExceptionContainsReferenceInfo(): void
    {
        $definition = new RefersToDefinition(
            path: 'data.tagId',
            targetType: 'tag',
            targetField: 'uuid',
            onDelete: OnDeleteBehavior::Restrict
        );

        $this->schemaService->method('getRefersToDefinitions')->willReturn([$definition]);

        $targetUuid = '01234567-89ab-cdef-0123-456789abcdef';

        $this->metaObjectRepository->method('checkUuidsExist')
            ->willReturn([
                $targetUuid => false,
            ]);

        try {
            $this->validator->validate(
                [
                    'tagId' => $targetUuid,
                ],
                'tag-binding',
                1,
                'org-123'
            );
            $this->fail('Expected ReferenceValidationException');
        } catch (ReferenceValidationException $e) {
            $ref = $e->getRef();
            $this->assertSame('data.tagId', $ref['path']);
            $this->assertSame('tag', $ref['type']);
            $this->assertSame($targetUuid, $ref['uuid']);
        }
    }

    public function testValidationIgnoresEmptyValues(): void
    {
        $definition = new RefersToDefinition(
            path: 'data.tagId',
            targetType: 'tag',
            targetField: 'uuid',
            onDelete: OnDeleteBehavior::Restrict
        );

        $this->schemaService->method('getRefersToDefinitions')->willReturn([$definition]);

        // Should not be called since value is empty
        $this->metaObjectRepository->expects($this->never())->method('checkUuidsExist');

        $this->validator->validate(
            [
                'tagId' => '',
            ],
            'tag-binding',
            1,
            'org-123'
        );

        // Also test with null
        $this->validator->validate(
            [
                'tagId' => null,
            ],
            'tag-binding',
            1,
            'org-123'
        );

        // And missing key
        $this->validator->validate(
            [
                'otherField' => 'value',
            ],
            'tag-binding',
            1,
            'org-123'
        );

        $this->assertTrue(true);
    }

    public function testValidationHandlesArrayOfReferences(): void
    {
        $definition = new RefersToDefinition(
            path: 'data.tagIds',
            targetType: 'tag',
            targetField: 'uuid',
            onDelete: OnDeleteBehavior::Restrict
        );

        $this->schemaService->method('getRefersToDefinitions')->willReturn([$definition]);

        $uuid1 = '01234567-89ab-cdef-0123-456789abcdef';
        $uuid2 = 'fedcba98-7654-3210-fedc-ba9876543210';

        $this->metaObjectRepository->method('checkUuidsExist')
            ->willReturn([
                $uuid1 => true,
                $uuid2 => true,
            ]);

        $this->validator->validate(
            [
                'tagIds' => [$uuid1, $uuid2],
            ],
            'tag-binding',
            1,
            'org-123'
        );

        $this->assertTrue(true);
    }

    public function testValidationHandlesNestedObjectReference(): void
    {
        $definition = new RefersToDefinition(
            path: 'data.metadata.tagId',
            targetType: 'tag',
            targetField: 'uuid',
            onDelete: OnDeleteBehavior::Restrict
        );

        $this->schemaService->method('getRefersToDefinitions')->willReturn([$definition]);

        $targetUuid = '01234567-89ab-cdef-0123-456789abcdef';

        $this->metaObjectRepository->method('checkUuidsExist')
            ->willReturn([
                $targetUuid => true,
            ]);

        $this->validator->validate(
            [
                'metadata' => [
                    'tagId' => $targetUuid,
                ],
            ],
            'some-type',
            1,
            'org-123'
        );

        $this->assertTrue(true);
    }

    public function testValidationHandlesArrayOfObjectsWithReferences(): void
    {
        $definition = new RefersToDefinition(
            path: 'data.items.tagId',
            targetType: 'tag',
            targetField: 'uuid',
            onDelete: OnDeleteBehavior::Restrict
        );

        $this->schemaService->method('getRefersToDefinitions')->willReturn([$definition]);

        $uuid1 = '01234567-89ab-cdef-0123-456789abcdef';
        $uuid2 = 'fedcba98-7654-3210-fedc-ba9876543210';

        $this->metaObjectRepository->method('checkUuidsExist')
            ->willReturn([
                $uuid1 => true,
                $uuid2 => true,
            ]);

        $this->validator->validate(
            [
                'items' => [
                    [
                        'tagId' => $uuid1,
                    ],
                    [
                        'tagId' => $uuid2,
                    ],
                ],
            ],
            'some-type',
            1,
            'org-123'
        );

        $this->assertTrue(true);
    }

    public function testValidationWithMultipleRefersToDefinitions(): void
    {
        $tagDefinition = new RefersToDefinition(
            path: 'data.tagId',
            targetType: 'tag',
            targetField: 'uuid',
            onDelete: OnDeleteBehavior::Restrict
        );

        $categoryDefinition = new RefersToDefinition(
            path: 'data.categoryId',
            targetType: 'category',
            targetField: 'uuid',
            onDelete: OnDeleteBehavior::Cascade
        );

        $this->schemaService->method('getRefersToDefinitions')
            ->willReturn([$tagDefinition, $categoryDefinition]);

        $tagUuid = '01234567-89ab-cdef-0123-456789abcdef';
        $categoryUuid = 'fedcba98-7654-3210-fedc-ba9876543210';

        $this->metaObjectRepository->method('checkUuidsExist')
            ->willReturnCallback(function (array $uuids, string $type) use ($tagUuid, $categoryUuid) {
                if ($type === 'tag') {
                    return [
                        $tagUuid => true,
                    ];
                }
                if ($type === 'category') {
                    return [
                        $categoryUuid => true,
                    ];
                }

                return [];
            });

        $this->validator->validate(
            [
                'tagId' => $tagUuid,
                'categoryId' => $categoryUuid,
            ],
            'some-type',
            1,
            'org-123'
        );

        $this->assertTrue(true);
    }
}
