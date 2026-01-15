<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\Service;

use Bareapi\Entity\Schema;
use Bareapi\Exception\SchemaNotFoundException;
use Bareapi\Repository\SchemaRepositoryInterface;
use Bareapi\Service\SchemaService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SchemaServiceTest extends TestCase
{
    /**
     * @var SchemaRepositoryInterface&MockObject
     */
    private SchemaRepositoryInterface $repository;

    private SchemaService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(SchemaRepositoryInterface::class);
        $this->service = new SchemaService($this->repository);
    }

    public function testGetDefaultSchemaReturnsSchema(): void
    {
        $schema = new Schema('notes', '1.0.0', [
            'type' => 'object',
        ]);
        $schema->setIsDefault(true);

        $this->repository->expects($this->once())
            ->method('findDefaultSchema')
            ->with('notes')
            ->willReturn($schema);

        $result = $this->service->getDefaultSchema('notes');
        $this->assertSame($schema, $result);
    }

    public function testGetDefaultSchemaThrowsIfNotFound(): void
    {
        $this->repository->expects($this->once())
            ->method('findDefaultSchema')
            ->with('notes')
            ->willReturn(null);

        $this->expectException(SchemaNotFoundException::class);
        $this->service->getDefaultSchema('notes');
    }

    public function testGetSchemaByVersionReturnsSchema(): void
    {
        $schema = new Schema('notes', '1.0.0', [
            'type' => 'object',
        ]);

        $this->repository->expects($this->once())
            ->method('findByVersion')
            ->with('notes', '1.0.0')
            ->willReturn($schema);

        $result = $this->service->getSchemaByVersion('notes', '1.0.0');
        $this->assertSame($schema, $result);
    }

    public function testGetSchemaByVersionThrowsIfNotFound(): void
    {
        $this->repository->expects($this->once())
            ->method('findByVersion')
            ->with('notes', '2.0.0')
            ->willReturn(null);

        $this->expectException(SchemaNotFoundException::class);
        $this->service->getSchemaByVersion('notes', '2.0.0');
    }

    public function testReturnsFilterableFields(): void
    {
        $schemaData = [
            'properties' => [
                'foo' => [
                    'type' => 'string',
                    'x-filterable' => true,
                ],
                'bar' => [
                    'type' => 'int',
                ],
                'baz' => [
                    'type' => 'string',
                    'x-filterable' => true,
                ],
            ],
        ];
        $schema = new Schema('notes', '1.0.0', $schemaData);
        $schema->setIsDefault(true);

        $this->repository->expects($this->once())
            ->method('findDefaultSchema')
            ->with('notes')
            ->willReturn($schema);

        $fields = $this->service->getFilterableFields('notes');
        $this->assertSame(['foo', 'baz'], $fields);
    }

    public function testReturnsEmptyArrayIfNoFilterableFields(): void
    {
        $schemaData = [
            'properties' => [
                'foo' => [
                    'type' => 'string',
                ],
                'bar' => [
                    'type' => 'int',
                ],
            ],
        ];
        $schema = new Schema('notes', '1.0.0', $schemaData);
        $schema->setIsDefault(true);

        $this->repository->expects($this->once())
            ->method('findDefaultSchema')
            ->with('notes')
            ->willReturn($schema);

        $fields = $this->service->getFilterableFields('notes');
        $this->assertSame([], $fields);
    }

    public function testListSchemasReturnsSchemas(): void
    {
        $schemas = [
            new Schema('notes', '1.0.0', [
                'type' => 'object',
            ]),
            new Schema('notes', '2.0.0', [
                'type' => 'object',
            ]),
        ];

        $this->repository->expects($this->once())
            ->method('findByObjectType')
            ->with('notes')
            ->willReturn($schemas);

        $result = $this->service->listSchemas('notes');
        $this->assertCount(2, $result);
    }

    public function testListObjectTypesReturnsTypes(): void
    {
        $types = ['notes', 'tasks', 'users'];

        $this->repository->expects($this->once())
            ->method('findAllObjectTypes')
            ->willReturn($types);

        $result = $this->service->listObjectTypes();
        $this->assertSame($types, $result);
    }

    public function testSchemaExistsReturnsTrueIfFound(): void
    {
        $schema = new Schema('notes', '1.0.0', [
            'type' => 'object',
        ]);
        $schema->setIsDefault(true);

        $this->repository->expects($this->once())
            ->method('findDefaultSchema')
            ->with('notes')
            ->willReturn($schema);

        $this->assertTrue($this->service->schemaExists('notes'));
    }

    public function testSchemaExistsReturnsFalseIfNotFound(): void
    {
        $this->repository->expects($this->once())
            ->method('findDefaultSchema')
            ->with('missing')
            ->willReturn(null);

        $this->assertFalse($this->service->schemaExists('missing'));
    }
}
