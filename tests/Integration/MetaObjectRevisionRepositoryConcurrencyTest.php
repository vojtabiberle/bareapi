<?php

declare(strict_types=1);

namespace Bareapi\Tests\Integration;

use Bareapi\Entity\MetaObject;
use Bareapi\Repository\MetaObjectRevisionRepository;
use Bareapi\Tests\RefreshDatabaseForKernelTestTrait;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MetaObjectRevisionRepositoryConcurrencyTest extends KernelTestCase
{
    use RefreshDatabaseForKernelTestTrait;

    public function testCreateNextLocksObjectRowBeforeAllocatingRevisionNumber(): void
    {
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);

        $object = MetaObject::fromStorage(
            '018ff3ae-c558-7ed8-8f68-0242ac1200aa',
            'notes',
            '1.0.0',
            [
                'title' => 'Initial',
                'content' => 'Initial content',
            ],
            'Locked note',
            'main',
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
            null,
        );

        $this->insertObject($connection, $object);
        $repository = self::getContainer()->get(MetaObjectRevisionRepository::class);
        self::assertInstanceOf(MetaObjectRevisionRepository::class, $repository);
        $repository->createInitial($object, $object->getData());

        $locker = DriverManager::getConnection($connection->getParams());
        $locker->beginTransaction();
        try {
            $locker->executeQuery(
                'SELECT id FROM meta_objects WHERE id = :id FOR UPDATE',
                [
                    'id' => $object->getId()->toString(),
                ],
            );
            $connection->executeStatement('SET lock_timeout TO 100');

            $this->expectException(\Doctrine\DBAL\Exception\DriverException::class);
            $repository->createNext($object, [
                'title' => 'Updated',
                'content' => 'Updated content',
            ]);
        } finally {
            if ($locker->isTransactionActive()) {
                $locker->rollBack();
            }
            $locker->close();
            $connection->executeStatement('SET lock_timeout TO DEFAULT');
        }
    }

    private function insertObject(Connection $connection, MetaObject $object): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $connection->insert('meta_objects', [
            'id' => $object->getId()->toString(),
            'type' => $object->getType(),
            'object_type' => $object->getObjectType(),
            'schema_version' => $object->getSchemaVersion(),
            'branch' => $object->getBranch(),
            'name' => $object->getName(),
            'data' => json_encode($object->getData(), JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
            'last_updated' => $now,
        ]);
    }
}
