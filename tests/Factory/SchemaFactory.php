<?php

declare(strict_types=1);

namespace Bareapi\Tests\Factory;

use Bareapi\Entity\Schema;

final class SchemaFactory
{
    /**
     * Create a valid Schema entity for testing.
     *
     * @param array<string, mixed>|null $schema JSON Schema definition
     */
    public static function create(
        string $objectType = 'notes',
        string $version = '1.0.0',
        ?array $schema = null,
        bool $isDefault = true,
        ?string $description = null,
    ): Schema {
        $defaultSchema = [
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type' => 'string',
                ],
                'content' => [
                    'type' => 'string',
                ],
            ],
            'required' => ['title'],
        ];

        $schemaEntity = new Schema($objectType, $version, $schema ?? $defaultSchema);
        $schemaEntity->setIsDefault($isDefault);

        if ($description !== null) {
            $schemaEntity->setDescription($description);
        }

        return $schemaEntity;
    }

    /**
     * Create a Schema with ACL rules for authorization testing.
     *
     * @param array<string, mixed> $aclRules ACL configuration
     * @param array<string, mixed>|null $additionalProperties Additional schema properties
     */
    public static function createWithAcl(
        string $objectType,
        array $aclRules,
        string $version = '1.0.0',
        bool $isDefault = true,
        ?array $additionalProperties = null,
    ): Schema {
        $schema = [
            'type' => 'object',
            'properties' => array_merge(
                [
                    'title' => [
                        'type' => 'string',
                    ],
                ],
                $additionalProperties ?? []
            ),
            'required' => ['title'],
            'x-metastore.acl' => $aclRules,
        ];

        return self::create($objectType, $version, $schema, $isDefault);
    }

    /**
     * Create a Schema with filterable fields for search testing.
     *
     * @param array<string, array<string, mixed>> $filterableFields Field definitions with x-filterable: true
     */
    public static function createWithFilterableFields(
        string $objectType,
        array $filterableFields,
        string $version = '1.0.0',
        bool $isDefault = true,
    ): Schema {
        $properties = [
            'title' => [
                'type' => 'string',
            ],
        ];

        foreach ($filterableFields as $fieldName => $fieldDef) {
            $properties[$fieldName] = array_merge($fieldDef, [
                'x-filterable' => true,
            ]);
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
            'required' => ['title'],
        ];

        return self::create($objectType, $version, $schema, $isDefault);
    }

    /**
     * Create a minimal Schema for simple tests.
     */
    public static function createMinimal(
        string $objectType = 'simple',
        string $version = '1.0.0',
    ): Schema {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                ],
            ],
        ];

        return self::create($objectType, $version, $schema, true);
    }
}
