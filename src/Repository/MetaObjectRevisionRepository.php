<?php

declare(strict_types=1);

namespace Bareapi\Repository;

use Bareapi\Entity\MetaObject;
use Bareapi\Entity\MetaObjectRevision;
use Bareapi\Exception\RevisionNotFoundException;
use Doctrine\DBAL\Connection;

final class MetaObjectRevisionRepository
{
    public function __construct(
        private Connection $connection
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createInitial(MetaObject $object, array $data): MetaObjectRevision
    {
        return $this->insert($object->getId()->toString(), 1, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createNext(MetaObject $object, array $data): MetaObjectRevision
    {
        return $this->connection->transactional(function () use ($object, $data): MetaObjectRevision {
            $uuid = $object->getId()->toString();
            $this->lockObject($uuid);
            $revision = $this->latestRevisionNumber($uuid) + 1;

            return $this->insert($uuid, $revision, $data);
        });
    }

    public function get(string $uuid, int $revision): MetaObjectRevision
    {
        $row = $this->connection->fetchAssociative(
            'SELECT uuid, revision, data, created_at FROM meta_object_revisions WHERE uuid = :uuid AND revision = :revision AND deleted_at IS NULL',
            [
                'uuid' => $uuid,
                'revision' => $revision,
            ],
        );
        if (! is_array($row)) {
            throw new RevisionNotFoundException($uuid, $revision);
        }

        return $this->hydrate($row);
    }

    public function latest(string $uuid): ?MetaObjectRevision
    {
        $row = $this->connection->fetchAssociative(
            'SELECT uuid, revision, data, created_at FROM meta_object_revisions WHERE uuid = :uuid AND deleted_at IS NULL ORDER BY revision DESC LIMIT 1',
            [
                'uuid' => $uuid,
            ],
        );
        if (! is_array($row)) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function latestRevisionNumber(string $uuid): int
    {
        $latest = $this->connection->fetchOne(
            'SELECT COALESCE(MAX(revision), 0) FROM meta_object_revisions WHERE uuid = :uuid AND deleted_at IS NULL',
            [
                'uuid' => $uuid,
            ],
        );

        return is_numeric($latest) ? (int) $latest : 0;
    }

    public function softDelete(string $uuid, int $revision): void
    {
        $this->connection->executeStatement(
            'UPDATE meta_object_revisions SET deleted_at = :deleted_at WHERE uuid = :uuid AND revision = :revision AND deleted_at IS NULL',
            [
                'deleted_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'uuid' => $uuid,
                'revision' => $revision,
            ],
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function insert(string $uuid, int $revision, array $data): MetaObjectRevision
    {
        $createdAt = new \DateTimeImmutable();
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO meta_object_revisions (uuid, revision, data, created_at)
                VALUES (:uuid, :revision, :data, :created_at)
            SQL,
            [
                'uuid' => $uuid,
                'revision' => $revision,
                'data' => json_encode($data, JSON_THROW_ON_ERROR),
                'created_at' => $createdAt->format('Y-m-d H:i:s'),
            ],
        );

        return new MetaObjectRevision($uuid, $revision, $data, $createdAt);
    }

    private function lockObject(string $uuid): void
    {
        $this->connection->fetchOne(
            'SELECT id FROM meta_objects WHERE id = :uuid FOR UPDATE',
            [
                'uuid' => $uuid,
            ],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): MetaObjectRevision
    {
        $data = json_decode($this->stringValue($row['data'] ?? null), true, 512, JSON_THROW_ON_ERROR);

        return new MetaObjectRevision(
            $this->stringValue($row['uuid'] ?? null),
            $this->intValue($row['revision'] ?? null),
            is_array($data) ? $this->stringKeyedArray($data) : [],
            new \DateTimeImmutable($this->stringValue($row['created_at'] ?? null)),
        );
    }

    private function stringValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return '';
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @param array<mixed> $array
     * @return array<string, mixed>
     */
    private function stringKeyedArray(array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
