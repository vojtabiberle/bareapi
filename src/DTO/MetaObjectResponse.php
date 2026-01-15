<?php

declare(strict_types=1);

namespace Bareapi\DTO;

use Bareapi\Entity\MetaObject;
use Bareapi\Entity\MetaObjectRevision;
use DateTimeImmutable;

class MetaObjectResponse
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $objectType,
        public readonly string $schemaVersion,
        public readonly string $branch,
        public readonly string $name,
        public readonly ?int $projectId,
        public readonly string $organizationId,
        public readonly DateTimeImmutable $lastUpdated,
        public readonly DateTimeImmutable $createdAt,
        public readonly int $revision,
        public readonly array $data,
        public readonly DateTimeImmutable $revisionCreatedAt,
    ) {
    }

    public static function fromEntity(MetaObject $metaObject, ?MetaObjectRevision $revision = null): self
    {
        $revision = $revision ?? $metaObject->getLatestRevision();

        if ($revision === null) {
            throw new \InvalidArgumentException('MetaObject must have at least one revision');
        }

        return new self(
            uuid: $metaObject->getUuid()->toString(),
            objectType: $metaObject->getObjectType(),
            schemaVersion: $metaObject->getSchemaVersion(),
            branch: $metaObject->getBranch(),
            name: $metaObject->getName(),
            projectId: $metaObject->getProjectId(),
            organizationId: $metaObject->getOrganizationId(),
            lastUpdated: $metaObject->getLastUpdated(),
            createdAt: $metaObject->getCreatedAt(),
            revision: $revision->getRevision(),
            data: $revision->getData(),
            revisionCreatedAt: $revision->getCreatedAt(),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        /** @var array<string, mixed> $data */
        $data = [];
        if (isset($row['data'])) {
            if (is_array($row['data'])) {
                /** @var array<string, mixed> $rowData */
                $rowData = $row['data'];
                $data = $rowData;
            } elseif (is_string($row['data'])) {
                $decoded = json_decode($row['data'], true);
                /** @var array<string, mixed> $decodedData */
                $decodedData = is_array($decoded) ? $decoded : [];
                $data = $decodedData;
            }
        }

        return new self(
            uuid: is_string($row['uuid'] ?? null) ? $row['uuid'] : '',
            objectType: is_string($row['object_type'] ?? null) ? $row['object_type'] : '',
            schemaVersion: is_string($row['schema_version'] ?? null) ? $row['schema_version'] : '',
            branch: is_string($row['branch'] ?? null) ? $row['branch'] : 'main',
            name: is_string($row['name'] ?? null) ? $row['name'] : '',
            projectId: isset($row['project_id']) && is_numeric($row['project_id']) ? (int) $row['project_id'] : null,
            organizationId: is_string($row['organization_id'] ?? null) ? $row['organization_id'] : '',
            lastUpdated: self::parseDateTime($row['last_updated'] ?? null),
            createdAt: self::parseDateTime($row['created_at'] ?? null),
            revision: isset($row['revision']) && is_numeric($row['revision']) ? (int) $row['revision'] : 1,
            data: $data,
            revisionCreatedAt: self::parseDateTime($row['revision_created_at'] ?? $row['created_at'] ?? null),
        );
    }

    private static function parseDateTime(mixed $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if (is_string($value)) {
            $parsed = DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $value);
            if ($parsed !== false) {
                return $parsed;
            }

            $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.uP', $value);
            if ($parsed !== false) {
                return $parsed;
            }

            $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:sP', $value);
            if ($parsed !== false) {
                return $parsed;
            }
        }

        return new DateTimeImmutable();
    }
}
