<?php

declare(strict_types=1);

namespace Bareapi\Repository;

use Doctrine\DBAL\Connection;

final class MetaRefRepository
{
    public function __construct(
        private Connection $connection
    ) {
    }

    /**
     * @param array<int, array{path: string, toType: string, toUuid: string}> $references
     */
    public function replaceReferences(string $fromType, string $fromUuid, array $references): void
    {
        $this->connection->transactional(function () use ($fromType, $fromUuid, $references): void {
            $this->connection->executeStatement(
                'DELETE FROM meta_refs WHERE from_type = :from_type AND from_uuid = :from_uuid',
                [
                    'from_type' => $fromType,
                    'from_uuid' => $fromUuid,
                ],
            );

            foreach ($references as $reference) {
                $this->connection->executeStatement(
                    <<<'SQL'
                        INSERT INTO meta_refs (from_type, from_uuid, path, to_type, to_uuid, created_at)
                        VALUES (:from_type, :from_uuid, :path, :to_type, :to_uuid, :created_at)
                    SQL,
                    [
                        'from_type' => $fromType,
                        'from_uuid' => $fromUuid,
                        'path' => $reference['path'],
                        'to_type' => $reference['toType'],
                        'to_uuid' => $reference['toUuid'],
                        'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                    ],
                );
            }
        });
    }

    /**
     * @return array<int, array{from_type: string, from_uuid: string, path: string}>
     */
    public function inboundReferences(string $toType, string $toUuid): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT from_type, from_uuid, path FROM meta_refs WHERE to_type = :to_type AND to_uuid = :to_uuid',
            [
                'to_type' => $toType,
                'to_uuid' => $toUuid,
            ],
        );

        return array_map(
            static fn (array $row): array => [
                'from_type' => self::stringValue($row['from_type'] ?? null),
                'from_uuid' => self::stringValue($row['from_uuid'] ?? null),
                'path' => self::stringValue($row['path'] ?? null),
            ],
            $rows
        );
    }

    public function deleteReferencesForObject(string $type, string $uuid): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                DELETE FROM meta_refs
                WHERE (from_type = :type AND from_uuid = :uuid)
                   OR (to_type = :type AND to_uuid = :uuid)
            SQL,
            [
                'type' => $type,
                'uuid' => $uuid,
            ],
        );
    }

    private static function stringValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return '';
    }
}
