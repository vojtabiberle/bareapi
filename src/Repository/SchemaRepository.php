<?php

declare(strict_types=1);

namespace Bareapi\Repository;

use Bareapi\Entity\Schema;
use Bareapi\Exception\SchemaNotFoundException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Uid\Uuid;

final class SchemaRepository
{
    public function __construct(
        private Connection $connection
    ) {
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, mixed>|null $createdBy
     */
    public function save(
        string $objectType,
        string $version,
        bool $isDefault,
        array $schema,
        ?string $description = null,
        ?array $createdBy = null,
    ): Schema {
        $now = new \DateTimeImmutable();
        $schemaJson = json_encode($schema, JSON_THROW_ON_ERROR);
        $createdByJson = $createdBy === null ? null : json_encode($createdBy, JSON_THROW_ON_ERROR);

        $this->connection->transactional(function () use (
            $objectType,
            $version,
            $isDefault,
            $schemaJson,
            $description,
            $createdByJson,
            $now,
        ): void {
            if ($isDefault) {
                $this->connection->executeStatement(
                    'UPDATE schemas SET is_default = FALSE, updated_at = :updated_at WHERE object_type = :object_type',
                    [
                        'updated_at' => $now->format('Y-m-d H:i:s'),
                        'object_type' => $objectType,
                    ],
                );
            }

            $this->connection->executeStatement(
                <<<'SQL'
                    INSERT INTO schemas (id, object_type, version, is_default, schema, description, created_at, updated_at, created_by)
                    VALUES (:id, :object_type, :version, :is_default, :schema, :description, :created_at, :updated_at, :created_by)
                    ON CONFLICT (object_type, version) DO UPDATE
                    SET is_default = EXCLUDED.is_default,
                        schema = EXCLUDED.schema,
                        description = EXCLUDED.description,
                        updated_at = EXCLUDED.updated_at,
                        created_by = EXCLUDED.created_by
                SQL,
                [
                    'id' => Uuid::v7()->toRfc4122(),
                    'object_type' => $objectType,
                    'version' => $version,
                    'is_default' => $isDefault,
                    'schema' => $schemaJson,
                    'description' => $description,
                    'created_at' => $now->format('Y-m-d H:i:s'),
                    'updated_at' => $now->format('Y-m-d H:i:s'),
                    'created_by' => $createdByJson,
                ],
                [
                    'is_default' => ParameterType::BOOLEAN,
                ],
            );
        });

        return $this->getByObjectType($objectType, $version);
    }

    public function getByObjectType(string $objectType, ?string $version = null): Schema
    {
        $query = 'SELECT * FROM schemas WHERE object_type = :object_type';
        $params = [
            'object_type' => $objectType,
        ];

        if ($version !== null && $version !== '') {
            $query .= ' AND version = :version';
            $params['version'] = $version;
        } else {
            $query .= ' AND is_default = TRUE';
        }

        $query .= ' LIMIT 1';
        $row = $this->connection->fetchAssociative($query, $params);
        if (! is_array($row)) {
            throw new SchemaNotFoundException($objectType);
        }

        return $this->hydrate($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Schema
    {
        $schema = json_decode($this->stringValue($row['schema'] ?? null), true, 512, JSON_THROW_ON_ERROR);
        $createdByRaw = $row['created_by'] ?? null;
        $createdBy = $createdByRaw === null
            ? null
            : json_decode($this->stringValue($createdByRaw), true, 512, JSON_THROW_ON_ERROR);

        return new Schema(
            $this->stringValue($row['id'] ?? null),
            $this->stringValue($row['object_type'] ?? null),
            $this->stringValue($row['version'] ?? null),
            $this->boolValue($row['is_default'] ?? null),
            is_array($schema) ? $this->stringKeyedArray($schema) : [],
            is_string($row['description']) ? $row['description'] : null,
            new \DateTimeImmutable($this->stringValue($row['created_at'] ?? null)),
            new \DateTimeImmutable($this->stringValue($row['updated_at'] ?? null)),
            is_array($createdBy) ? $this->stringKeyedArray($createdBy) : null,
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

    private function boolValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 't'], true);
        }

        return false;
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
