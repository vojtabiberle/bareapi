<?php

declare(strict_types=1);

namespace Bareapi\Tests;

use Doctrine\ORM\EntityManagerInterface;

trait RefreshDatabaseForWebTestTrait
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->refreshDatabase();
    }

    protected function refreshDatabase(): void
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $em->getConnection();

        $connection->beginTransaction();
        try {
            // Disable referential integrity
            $connection->executeStatement('SET session_replication_role = replica');

            // Truncate meta_objects table
            $connection->executeStatement('TRUNCATE TABLE "meta_objects" RESTART IDENTITY CASCADE');

            // Re-enable referential integrity
            $connection->executeStatement('SET session_replication_role = DEFAULT');

            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }
}
