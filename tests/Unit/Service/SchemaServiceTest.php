<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\Service;

use Bareapi\Exception\SchemaNotFoundException;
use Bareapi\Service\SchemaService;
use PHPUnit\Framework\TestCase;

final class SchemaServiceTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/bareapi_schema_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->tmpDir . '/*.json') as $file) {
            if (is_string($file)) {
                unlink($file);
            }
        }
        rmdir($this->tmpDir);
    }

    public function testReturnsFilterableFields(): void
    {
        $schema = [
            'properties' => [
                'foo' => [
                    'type' => 'string',
                    'x-filterable' => true,
                ],
                'bar' => [
                    'type' => 'int',
                ],
                'baz' => [
                    'type' => 'string',
                    'x-filterable' => true,
                ],
            ],
        ];
        $json = json_encode($schema);
        $this->assertIsString($json, 'json_encode failed');
        file_put_contents($this->tmpDir . '/notes.json', $json);

        $service = new SchemaService($this->tmpDir);
        $fields = $service->getFilterableFields('notes');
        $this->assertSame(['foo', 'baz'], $fields);
    }

    public function testReturnsEmptyArrayIfNoFilterableFields(): void
    {
        $schema = [
            'properties' => [
                'foo' => [
                    'type' => 'string',
                ],
                'bar' => [
                    'type' => 'int',
                ],
            ],
        ];
        $json = json_encode($schema);
        $this->assertIsString($json, 'json_encode failed');
        file_put_contents($this->tmpDir . '/notes.json', $json);

        $service = new SchemaService($this->tmpDir);
        $fields = $service->getFilterableFields('notes');
        $this->assertSame([], $fields);
    }

    public function testThrowsIfSchemaFileMissing(): void
    {
        $service = new SchemaService($this->tmpDir);
        $this->expectException(SchemaNotFoundException::class);
        $service->getFilterableFields('missing');
    }

    public function testThrowsIfSchemaFileInvalidJson(): void
    {
        file_put_contents($this->tmpDir . '/notes.json', '{invalid json}');
        $service = new SchemaService($this->tmpDir);
        $this->expectException(\JsonException::class);
        $service->getFilterableFields('notes');
    }
}
