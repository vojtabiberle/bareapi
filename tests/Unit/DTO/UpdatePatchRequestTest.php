<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\DTO;

use Bareapi\DTO\UpdatePatchRequest;
use PHPUnit\Framework\TestCase;

final class UpdatePatchRequestTest extends TestCase
{
    public function testConstructorStoresDataAndSchemaVersion(): void
    {
        $request = new UpdatePatchRequest(
            data: ['title' => 'Updated'],
            schemaVersion: '2.0.0'
        );

        $this->assertSame(['title' => 'Updated'], $request->data);
        $this->assertSame('2.0.0', $request->schemaVersion);
    }

    public function testFromArrayExtractsDataArrayCorrectly(): void
    {
        $data = ['content' => 'New content'];
        $request = UpdatePatchRequest::fromArray(['data' => $data]);

        $this->assertSame($data, $request->data);
    }

    public function testFromArrayExtractsSchemaVersionWhenPresent(): void
    {
        $request = UpdatePatchRequest::fromArray([
            'data' => [],
            'schemaVersion' => '1.5.0',
        ]);

        $this->assertSame('1.5.0', $request->schemaVersion);
    }

    public function testFromArrayReturnsEmptyArrayForMissingData(): void
    {
        $request = UpdatePatchRequest::fromArray([]);

        $this->assertSame([], $request->data);
    }

    public function testFromArrayReturnsNullForMissingSchemaVersion(): void
    {
        $request = UpdatePatchRequest::fromArray(['data' => []]);

        $this->assertNull($request->schemaVersion);
    }

    public function testFromArrayHandlesNonArrayDataValues(): void
    {
        $request = UpdatePatchRequest::fromArray(['data' => 'string-value']);

        $this->assertSame([], $request->data);
    }

    public function testFromArrayHandlesNonStringSchemaVersion(): void
    {
        $request = UpdatePatchRequest::fromArray([
            'data' => [],
            'schemaVersion' => 123,
        ]);

        $this->assertNull($request->schemaVersion);
    }

    public function testConstructorDefaultsSchemaVersionToNull(): void
    {
        $request = new UpdatePatchRequest(data: ['key' => 'value']);

        $this->assertNull($request->schemaVersion);
    }
}
