<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\Repository;

use Bareapi\Entity\MetaObject;
use Bareapi\Exception\InvalidFilterException;
use Bareapi\Repository\MetaObjectRepository;
use Bareapi\Service\SchemaService;
use Bareapi\Service\SchemaServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class MetaObjectRepositoryTest extends TestCase
{
    public function testFindReturnsEntityOrNull(): void
    {
        /** @var EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);
        $schemaService = new \Bareapi\Service\SchemaService(__DIR__ . '/../../../config/schemas/');
        $repo = new MetaObjectRepository($em, $schemaService);

        /** @var MetaObject&\PHPUnit\Framework\MockObject\MockObject $entity */
        $entity = $this->createMock(MetaObject::class);
        $em->method('find')->willReturnOnConsecutiveCalls($entity, null);

        $this->assertSame($entity, $repo->find('id1'));
        $this->assertNull($repo->find('id2'));
    }

    public function testFindAllByTypeReturnsEntities(): void
    {
        /** @var EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);
        $schemaService = new \Bareapi\Service\SchemaService(__DIR__ . '/../../../config/schemas/');
        $repo = new MetaObjectRepository($em, $schemaService);

        /** @var QueryBuilder&\PHPUnit\Framework\MockObject\MockObject $qb */
        $qb = $this->createMock(QueryBuilder::class);
        /** @var MetaObject&\PHPUnit\Framework\MockObject\MockObject $entity */
        $entity = $this->createMock(MetaObject::class);

        $em->method('createQueryBuilder')->willReturn($qb);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturnSelf();
        $qb->method('getResult')->willReturn([$entity, null, $entity]);

        $result = $repo->findAllByType('notes');
        $this->assertCount(2, $result);
    }

    // TODO: Refactor SchemaService to use an interface to allow mocking for this test.
    public function testFindByTypeAndFiltersThrowsOnInvalidFilter(): void
    {
        /** @var EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);
        /** @var SchemaServiceInterface&\PHPUnit\Framework\MockObject\MockObject $schemaService */
        $schemaService = $this->createMock(SchemaServiceInterface::class);
        $schemaService->method('getFilterableFields')->willReturn(['foo']);
        $repo = new MetaObjectRepository($em, $schemaService);

        $this->expectException(InvalidFilterException::class);
        $repo->findByTypeAndFilters('notes', [
            'bar' => 'baz',
        ]);
    }

    public function testSaveAndDeleteCallEntityManager(): void
    {
        /** @var EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);
        $schemaService = new \Bareapi\Service\SchemaService(__DIR__ . '/../../../config/schemas/');
        $repo = new MetaObjectRepository($em, $schemaService);

        /** @var MetaObject&\PHPUnit\Framework\MockObject\MockObject $entity */
        $entity = $this->createMock(MetaObject::class);

        $em->expects($this->once())->method('persist')->with($entity);
        $em->expects($this->once())->method('flush');
        $repo->save($entity);

        $em->expects($this->once())->method('remove')->with($entity);
        $em->expects($this->once())->method('flush');
        $repo->delete($entity);
    }
}
