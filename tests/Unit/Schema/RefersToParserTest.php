<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\Schema;

use Bareapi\Exception\InvalidRefersToException;
use Bareapi\Schema\OnDeleteBehavior;
use Bareapi\Schema\RefersToParser;
use PHPUnit\Framework\TestCase;

final class RefersToParserTest extends TestCase
{
    private RefersToParser $parser;

    protected function setUp(): void
    {
        $this->parser = new RefersToParser();
    }

    public function testReturnsEmptyArrayWhenNoProperties(): void
    {
        $schemaData = [
            'type' => 'object',
        ];

        $definitions = $this->parser->parse($schemaData);

        $this->assertSame([], $definitions);
    }

    public function testReturnsEmptyArrayWhenNoRefersTo(): void
    {
        $schemaData = [
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type' => 'string',
                ],
                'count' => [
                    'type' => 'integer',
                ],
            ],
        ];

        $definitions = $this->parser->parse($schemaData);

        $this->assertSame([], $definitions);
    }

    public function testParsesSimpleRefersToField(): void
    {
        $schemaData = [
            'type' => 'object',
            'properties' => [
                'tagId' => [
                    'type' => 'string',
                    'x-metastore' => [
                        'refersTo' => [
                            'type' => 'tag',
                            'field' => 'uuid',
                        ],
                    ],
                ],
            ],
        ];

        $definitions = $this->parser->parse($schemaData);

        $this->assertCount(1, $definitions);
        $this->assertSame('data.tagId', $definitions[0]->path);
        $this->assertSame('tag', $definitions[0]->targetType);
        $this->assertSame('uuid', $definitions[0]->targetField);
        $this->assertSame(OnDeleteBehavior::Restrict, $definitions[0]->onDelete);
    }

    public function testParsesRefersToWithCascadeOnDelete(): void
    {
        $schemaData = [
            'type' => 'object',
            'properties' => [
                'tagId' => [
                    'type' => 'string',
                    'x-metastore' => [
                        'refersTo' => [
                            'type' => 'tag',
                            'field' => 'uuid',
                        ],
                        'onDelete' => 'cascade',
                    ],
                ],
            ],
        ];

        $definitions = $this->parser->parse($schemaData);

        $this->assertCount(1, $definitions);
        $this->assertSame(OnDeleteBehavior::Cascade, $definitions[0]->onDelete);
    }

    public function testParsesRefersToWithExplicitRestrict(): void
    {
        $schemaData = [
            'type' => 'object',
            'properties' => [
                'tagId' => [
                    'type' => 'string',
                    'x-metastore' => [
                        'refersTo' => [
                            'type' => 'tag',
                            'field' => 'uuid',
                        ],
                        'onDelete' => 'restrict',
                    ],
                ],
            ],
        ];

        $definitions = $this->parser->parse($schemaData);

        $this->assertCount(1, $definitions);
        $this->assertSame(OnDeleteBehavior::Restrict, $definitions[0]->onDelete);
    }

    public function testParsesMultipleRefersToFields(): void
    {
        $schemaData = [
            'type' => 'object',
            'properties' => [
                'tagId' => [
                    'type' => 'string',
                    'x-metastore' => [
                        'refersTo' => [
                            'type' => 'tag',
                            'field' => 'uuid',
                        ],
                    ],
                ],
                'categoryId' => [
                    'type' => 'string',
                    'x-metastore' => [
                        'refersTo' => [
                            'type' => 'category',
                            'field' => 'uuid',
                        ],
                        'onDelete' => 'cascade',
                    ],
                ],
            ],
        ];

        $definitions = $this->parser->parse($schemaData);

        $this->assertCount(2, $definitions);

        $paths = array_map(fn ($d) => $d->path, $definitions);
        $this->assertContains('data.tagId', $paths);
        $this->assertContains('data.categoryId', $paths);
    }

    public function testParsesRefersToInNestedObject(): void
    {
        $schemaData = [
            'type' => 'object',
            'properties' => [
                'metadata' => [
                    'type' => 'object',
                    'properties' => [
                        'tagId' => [
                            'type' => 'string',
                            'x-metastore' => [
                                'refersTo' => [
                                    'type' => 'tag',
                                    'field' => 'uuid',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $definitions = $this->parser->parse($schemaData);

        $this->assertCount(1, $definitions);
        $this->assertSame('data.metadata.tagId', $definitions[0]->path);
        $this->assertSame('tag', $definitions[0]->targetType);
    }

    public function testParsesRefersToInArrayItems(): void
    {
        $schemaData = [
            'type' => 'object',
            'properties' => [
                'tagIds' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                        'x-metastore' => [
                            'refersTo' => [
                                'type' => 'tag',
                                'field' => 'uuid',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $definitions = $this->parser->parse($schemaData);

        $this->assertCount(1, $definitions);
        $this->assertSame('data.tagIds', $definitions[0]->path);
        $this->assertSame('tag', $definitions[0]->targetType);
    }

    public function testParsesRefersToInArrayOfObjects(): void
    {
        $schemaData = [
            'type' => 'object',
            'properties' => [
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'tagId' => [
                                'type' => 'string',
                                'x-metastore' => [
                                    'refersTo' => [
                                        'type' => 'tag',
                                        'field' => 'uuid',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $definitions = $this->parser->parse($schemaData);

        $this->assertCount(1, $definitions);
        $this->assertSame('data.items.tagId', $definitions[0]->path);
        $this->assertSame('tag', $definitions[0]->targetType);
    }

    public function testThrowsExceptionWhenRefersToFieldNotUuid(): void
    {
        $schemaData = [
            'type' => 'object',
            'properties' => [
                'tagId' => [
                    'type' => 'string',
                    'x-metastore' => [
                        'refersTo' => [
                            'type' => 'tag',
                            'field' => 'name',
                        ],
                    ],
                ],
            ],
        ];

        $this->expectException(InvalidRefersToException::class);
        $this->expectExceptionMessage('refersTo.field must be "uuid"');

        $this->parser->parse($schemaData);
    }

    public function testThrowsExceptionWhenRefersToTypeEmpty(): void
    {
        $schemaData = [
            'type' => 'object',
            'properties' => [
                'tagId' => [
                    'type' => 'string',
                    'x-metastore' => [
                        'refersTo' => [
                            'type' => '',
                            'field' => 'uuid',
                        ],
                    ],
                ],
            ],
        ];

        $this->expectException(InvalidRefersToException::class);
        $this->expectExceptionMessage('refersTo.type is required');

        $this->parser->parse($schemaData);
    }

    public function testThrowsExceptionWhenRefersToTypeMissing(): void
    {
        $schemaData = [
            'type' => 'object',
            'properties' => [
                'tagId' => [
                    'type' => 'string',
                    'x-metastore' => [
                        'refersTo' => [
                            'field' => 'uuid',
                        ],
                    ],
                ],
            ],
        ];

        $this->expectException(InvalidRefersToException::class);
        $this->expectExceptionMessage('refersTo.type is required');

        $this->parser->parse($schemaData);
    }

    public function testThrowsExceptionWhenOnDeleteInvalid(): void
    {
        $schemaData = [
            'type' => 'object',
            'properties' => [
                'tagId' => [
                    'type' => 'string',
                    'x-metastore' => [
                        'refersTo' => [
                            'type' => 'tag',
                            'field' => 'uuid',
                        ],
                        'onDelete' => 'invalid',
                    ],
                ],
            ],
        ];

        $this->expectException(InvalidRefersToException::class);
        $this->expectExceptionMessage("onDelete must be 'restrict' or 'cascade'");

        $this->parser->parse($schemaData);
    }

    public function testThrowsExceptionWhenRefersToNotObject(): void
    {
        $schemaData = [
            'type' => 'object',
            'properties' => [
                'tagId' => [
                    'type' => 'string',
                    'x-metastore' => [
                        'refersTo' => 'tag',
                    ],
                ],
            ],
        ];

        $this->expectException(InvalidRefersToException::class);
        $this->expectExceptionMessage('refersTo must be an object');

        $this->parser->parse($schemaData);
    }

    public function testIgnoresFieldsWithoutXMetastore(): void
    {
        $schemaData = [
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type' => 'string',
                ],
                'tagId' => [
                    'type' => 'string',
                    'x-metastore' => [
                        'refersTo' => [
                            'type' => 'tag',
                            'field' => 'uuid',
                        ],
                    ],
                ],
                'description' => [
                    'type' => 'string',
                ],
            ],
        ];

        $definitions = $this->parser->parse($schemaData);

        $this->assertCount(1, $definitions);
        $this->assertSame('data.tagId', $definitions[0]->path);
    }

    public function testIgnoresXMetastoreWithoutRefersTo(): void
    {
        $schemaData = [
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'x-metastore' => [
                        'acl' => [
                            'create' => [],
                        ],
                    ],
                ],
            ],
        ];

        $definitions = $this->parser->parse($schemaData);

        $this->assertSame([], $definitions);
    }
}
