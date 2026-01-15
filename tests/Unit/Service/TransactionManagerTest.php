<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\Service;

use Bareapi\Service\TransactionManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class TransactionManagerTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private TransactionManager $manager;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->manager = new TransactionManager($this->em);
    }

    public function testTransactionalBeginsTransactionBeforeCallback(): void
    {
        $callOrder = [];

        $this->em->expects($this->once())
            ->method('beginTransaction')
            ->willReturnCallback(function () use (&$callOrder) {
                $callOrder[] = 'beginTransaction';
            });

        $this->em->expects($this->once())
            ->method('flush')
            ->willReturnCallback(function () use (&$callOrder) {
                $callOrder[] = 'flush';
            });

        $this->em->expects($this->once())
            ->method('commit')
            ->willReturnCallback(function () use (&$callOrder) {
                $callOrder[] = 'commit';
            });

        $this->manager->transactional(function () use (&$callOrder) {
            $callOrder[] = 'callback';
            return 'result';
        });

        $this->assertSame(['beginTransaction', 'callback', 'flush', 'commit'], $callOrder);
    }

    public function testTransactionalExecutesCallback(): void
    {
        $executed = false;

        $this->manager->transactional(function () use (&$executed) {
            $executed = true;
            return null;
        });

        $this->assertTrue($executed);
    }

    public function testTransactionalFlushesAfterCallback(): void
    {
        $this->em->expects($this->once())
            ->method('flush');

        $this->manager->transactional(fn () => 'result');
    }

    public function testTransactionalCommitsAfterFlush(): void
    {
        $this->em->expects($this->once())
            ->method('commit');

        $this->manager->transactional(fn () => 'result');
    }

    public function testTransactionalReturnsCallbackResult(): void
    {
        $result = $this->manager->transactional(fn () => ['key' => 'value']);

        $this->assertSame(['key' => 'value'], $result);
    }

    public function testTransactionalRollsBackOnException(): void
    {
        $this->em->expects($this->once())
            ->method('rollback');

        $this->em->expects($this->never())
            ->method('commit');

        $this->expectException(\RuntimeException::class);

        $this->manager->transactional(function () {
            throw new \RuntimeException('Test error');
        });
    }

    public function testTransactionalRethrowsExceptionAfterRollback(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Specific error message');

        $this->manager->transactional(function () {
            throw new \InvalidArgumentException('Specific error message');
        });
    }

    public function testFlushDelegatesToEntityManager(): void
    {
        $this->em->expects($this->once())
            ->method('flush');

        $this->manager->flush();
    }

    public function testPersistDelegatesToEntityManager(): void
    {
        $entity = new \stdClass();

        $this->em->expects($this->once())
            ->method('persist')
            ->with($this->identicalTo($entity));

        $this->manager->persist($entity);
    }

    public function testRemoveDelegatesToEntityManager(): void
    {
        $entity = new \stdClass();

        $this->em->expects($this->once())
            ->method('remove')
            ->with($this->identicalTo($entity));

        $this->manager->remove($entity);
    }

    public function testClearDelegatesToEntityManager(): void
    {
        $this->em->expects($this->once())
            ->method('clear');

        $this->manager->clear();
    }
}
