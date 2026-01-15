<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\DTO;

use Bareapi\DTO\UpdatePutRequest;
use PHPUnit\Framework\TestCase;

final class UpdatePutRequestTest extends TestCase
{
    public function testConstructorStoresAllPropertiesCorrectly(): void
    {
        $request = new UpdatePutRequest(
            name: 'updated-name',
            data: [
                'title' => 'New Title',
            ],
            schemaVersion: '2.0.0',
            branch: 'main'
        );

        $this->assertSame('updated-name', $request->name);
        $this->assertSame([
            'title' => 'New Title',
        ], $request->data);
        $this->assertSame('2.0.0', $request->schemaVersion);
        $this->assertSame('main', $request->branch);
    }

    public function testFromArrayExtractsNameCorrectly(): void
    {
        $request = UpdatePutRequest::fromArray([
            'name' => 'new-name',
            'data' => [],
        ]);

        $this->assertSame('new-name', $request->name);
    }

    public function testFromArrayExtractsDataArrayCorrectly(): void
    {
        $data = [
            'title' => 'Full Replacement',
            'content' => 'Complete data',
        ];
        $request = UpdatePutRequest::fromArray([
            'name' => 'test',
            'data' => $data,
        ]);

        $this->assertSame($data, $request->data);
    }

    public function testFromArrayExtractsSchemaVersionWhenPresent(): void
    {
        $request = UpdatePutRequest::fromArray([
            'name' => 'test',
            'data' => [],
            'schemaVersion' => '3.0.0',
        ]);

        $this->assertSame('3.0.0', $request->schemaVersion);
    }

    public function testFromArrayExtractsBranchWhenPresent(): void
    {
        $request = UpdatePutRequest::fromArray([
            'name' => 'test',
            'data' => [],
            'branch' => 'release',
        ]);

        $this->assertSame('release', $request->branch);
    }

    public function testFromArrayReturnsEmptyStringForMissingName(): void
    {
        $request = UpdatePutRequest::fromArray([
            'data' => [],
        ]);

        $this->assertSame('', $request->name);
    }

    public function testFromArrayReturnsEmptyArrayForMissingData(): void
    {
        $request = UpdatePutRequest::fromArray([
            'name' => 'test',
        ]);

        $this->assertSame([], $request->data);
    }

    public function testFromArrayReturnsNullForMissingOptionalFields(): void
    {
        $request = UpdatePutRequest::fromArray([
            'name' => 'test',
            'data' => [],
        ]);

        $this->assertNull($request->schemaVersion);
        $this->assertNull($request->branch);
    }

    public function testFromArrayHandlesNonStringName(): void
    {
        $request = UpdatePutRequest::fromArray([
            'name' => ['invalid'],
            'data' => [],
        ]);

        $this->assertSame('', $request->name);
    }

    public function testFromArrayHandlesNonArrayData(): void
    {
        $request = UpdatePutRequest::fromArray([
            'name' => 'test',
            'data' => 12345,
        ]);

        $this->assertSame([], $request->data);
    }

    public function testConstructorDefaultsOptionalFieldsToNull(): void
    {
        $request = new UpdatePutRequest(name: 'test', data: []);

        $this->assertNull($request->schemaVersion);
        $this->assertNull($request->branch);
    }
}
