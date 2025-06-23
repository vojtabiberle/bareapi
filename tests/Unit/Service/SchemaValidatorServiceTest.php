<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\Service;

use Bareapi\Exception\SchemaNotFoundException;
use Bareapi\Exception\ValidationException;
use Bareapi\Service\SchemaValidatorService;
use PHPUnit\Framework\TestCase;

final class SchemaValidatorServiceTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/bareapi_validator_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->tmpDir . '/config/schemas/*.json') as $file) {
            if (is_string($file)) {
                unlink($file);
            }
        }
        @rmdir($this->tmpDir . '/config/schemas');
        @rmdir($this->tmpDir . '/config');
        @rmdir($this->tmpDir);
    }

    public function testValidPayloadPassesValidation(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type' => 'string',
                ],
            ],
            'required' => ['title'],
        ];
        $this->writeSchema('notes', $schema);

        $service = new SchemaValidatorService($this->tmpDir);
        $payload = [
            'title' => 'Hello',
        ];
        $result = $service->validate('notes', $payload);
        $this->assertSame($payload, $result);
    }

    public function testInvalidPayloadThrowsValidationException(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type' => 'string',
                ],
            ],
            'required' => ['title'],
        ];
        $this->writeSchema('notes', $schema);

        $service = new SchemaValidatorService($this->tmpDir);
        $this->expectException(ValidationException::class);
        $service->validate('notes', []);
    }

    public function testMissingSchemaThrowsSchemaNotFoundException(): void
    {
        $service = new SchemaValidatorService($this->tmpDir);
        $this->expectException(SchemaNotFoundException::class);
        $service->validate('missing', [
            'foo' => 'bar',
        ]);
    }

    public function testMalformedSchemaThrowsSchemaNotFoundException(): void
    {
        $dir = $this->tmpDir . '/config/schemas';
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/notes.json', '{invalid json}');
        $service = new SchemaValidatorService($this->tmpDir);
        $this->expectException(SchemaNotFoundException::class);
        $service->validate('notes', [
            'foo' => 'bar',
        ]);
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function writeSchema(string $type, array $schema): void
    {
        $dir = $this->tmpDir . '/config/schemas';
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $json = json_encode($schema);
        $this->assertIsString($json, 'json_encode failed');
        file_put_contents($dir . '/' . $type . '.json', $json);
    }
}
