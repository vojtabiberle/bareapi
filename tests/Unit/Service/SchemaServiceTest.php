<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\Service;

use Bareapi\Exception\SchemaNotFoundException;
use Bareapi\Service\SchemaService;
use PHPUnit\Framework\TestCase;

final class SchemaServiceTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__ . '/fixtures/';
        if (! is_dir($this->fixturesDir)) {
            mkdir($this->fixturesDir, 0777, true);
        }
        // Create a test schema file
        file_put_contents($this->fixturesDir . 'testtype.json', json_encode([
            'properties' => [
                'foo' => [
                    'type' => 'string',
                    'x-filterable' => true,
                ],
                'bar' => [
                    'type' => 'integer',
                ],
                'baz' => [
                    'type' => 'string',
                    'x-filterable' => true,
                ],
            ],
        ]));
        file_put_contents($this->fixturesDir . 'nofilter.json', json_encode([
            'properties' => [
                'a' => [
                    'type' => 'string',
                ],
                'b' => [
                    'type' => 'integer',
                ],
            ],
        ]));
    }

    protected function tearDown(): void
    {
        foreach (['testtype.json', 'nofilter.json'] as $file) {
            $path = $this->fixturesDir . $file;
            if (is_file($path)) {
                unlink($path);
            }
        }
        if (is_dir($this->fixturesDir)) {
            rmdir($this->fixturesDir);
        }
    }

    public function testReturnsFilterableFields(): void
    {
        $service = new SchemaService($this->fixturesDir);
        $fields = $service->getFilterableFields('testtype');
        $this->assertEqualsCanonicalizing(['foo', 'baz'], $fields);
    }

    public function testReturnsEmptyArrayIfNoFilterableFields(): void
    {
        $service = new SchemaService($this->fixturesDir);
        $fields = $service->getFilterableFields('nofilter');
        $this->assertSame([], $fields);
    }

    public function testThrowsOnMissingSchema(): void
    {
        $service = new SchemaService($this->fixturesDir);
        $this->expectException(SchemaNotFoundException::class);
        $service->getFilterableFields('doesnotexist');
    }
}
