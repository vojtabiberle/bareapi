<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\Validation;

use Bareapi\Exception\ValidationException;
use Bareapi\Validation\JsonSchemaValidator;
use PHPUnit\Framework\TestCase;

final class JsonSchemaValidatorTest extends TestCase
{
    private JsonSchemaValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new JsonSchemaValidator();
    }

    public function testValidatesValidData(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                ],
                'age' => [
                    'type' => 'integer',
                ],
            ],
            'required' => ['name'],
        ];

        $data = [
            'name' => 'John',
            'age' => 30,
        ];

        $result = $this->validator->validate($data, $schema);
        $this->assertSame($data, $result);
    }

    public function testThrowsOnMissingRequiredField(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                ],
            ],
            'required' => ['name'],
        ];

        $data = [];

        $this->expectException(ValidationException::class);
        $this->validator->validate($data, $schema);
    }

    public function testThrowsOnWrongType(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'age' => [
                    'type' => 'integer',
                ],
            ],
        ];

        $data = [
            'age' => 'not a number',
        ];

        $this->expectException(ValidationException::class);
        $this->validator->validate($data, $schema);
    }

    public function testValidatesNestedObjects(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'address' => [
                    'type' => 'object',
                    'properties' => [
                        'city' => [
                            'type' => 'string',
                        ],
                        'zip' => [
                            'type' => 'string',
                        ],
                    ],
                    'required' => ['city'],
                ],
            ],
        ];

        $data = [
            'address' => [
                'city' => 'New York',
                'zip' => '10001',
            ],
        ];

        $result = $this->validator->validate($data, $schema);
        $this->assertSame($data, $result);
    }

    public function testValidatesArrays(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'tags' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                    ],
                ],
            ],
        ];

        $data = [
            'tags' => ['tag1', 'tag2', 'tag3'],
        ];

        $result = $this->validator->validate($data, $schema);
        $this->assertSame($data, $result);
    }

    public function testThrowsOnInvalidArrayItems(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'tags' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                    ],
                ],
            ],
        ];

        $data = [
            'tags' => ['tag1', 123, 'tag3'],
        ];

        $this->expectException(ValidationException::class);
        $this->validator->validate($data, $schema);
    }

    public function testValidatesWithPattern(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'email' => [
                    'type' => 'string',
                    'pattern' => '^[a-z]+@[a-z]+\\.[a-z]+$',
                ],
            ],
        ];

        $data = [
            'email' => 'test@example.com',
        ];
        $result = $this->validator->validate($data, $schema);
        $this->assertSame($data, $result);
    }

    public function testThrowsOnPatternMismatch(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'email' => [
                    'type' => 'string',
                    'pattern' => '^[a-z]+@[a-z]+\\.[a-z]+$',
                ],
            ],
        ];

        $data = [
            'email' => 'invalid-email',
        ];

        $this->expectException(ValidationException::class);
        $this->validator->validate($data, $schema);
    }

    public function testIsValidReturnsTrueForValidData(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                ],
            ],
        ];

        $data = [
            'name' => 'Test',
        ];

        $this->assertTrue($this->validator->isValid($data, $schema));
    }

    public function testIsValidReturnsFalseForInvalidData(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                ],
            ],
            'required' => ['name'],
        ];

        $data = [];

        $this->assertFalse($this->validator->isValid($data, $schema));
    }

    public function testValidatesEnumValues(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'enum' => ['active', 'inactive', 'pending'],
                ],
            ],
        ];

        $data = [
            'status' => 'active',
        ];
        $result = $this->validator->validate($data, $schema);
        $this->assertSame($data, $result);
    }

    public function testThrowsOnInvalidEnumValue(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'enum' => ['active', 'inactive'],
                ],
            ],
        ];

        $data = [
            'status' => 'invalid',
        ];

        $this->expectException(ValidationException::class);
        $this->validator->validate($data, $schema);
    }

    public function testValidatesMinMaxLength(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'username' => [
                    'type' => 'string',
                    'minLength' => 3,
                    'maxLength' => 20,
                ],
            ],
        ];

        $data = [
            'username' => 'john',
        ];
        $result = $this->validator->validate($data, $schema);
        $this->assertSame($data, $result);
    }

    public function testThrowsOnTooShortString(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'username' => [
                    'type' => 'string',
                    'minLength' => 3,
                ],
            ],
        ];

        $data = [
            'username' => 'ab',
        ];

        $this->expectException(ValidationException::class);
        $this->validator->validate($data, $schema);
    }
}
