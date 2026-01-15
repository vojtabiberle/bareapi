<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\DTO;

use Bareapi\DTO\CreateRequest;
use PHPUnit\Framework\TestCase;

final class CreateRequestTest extends TestCase
{
    public function testConstructorStoresAllPropertiesCorrectly(): void
    {
        $request = new CreateRequest(
            name: 'my-object',
            data: ['title' => 'Test'],
            schemaVersion: '1.0.0',
            branch: 'feature',
            scope: 'project'
        );

        $this->assertSame('my-object', $request->name);
        $this->assertSame(['title' => 'Test'], $request->data);
        $this->assertSame('1.0.0', $request->schemaVersion);
        $this->assertSame('feature', $request->branch);
        $this->assertSame('project', $request->scope);
    }

    public function testFromArrayExtractsNameCorrectly(): void
    {
        $request = CreateRequest::fromArray(['name' => 'test-name', 'data' => []]);

        $this->assertSame('test-name', $request->name);
    }

    public function testFromArrayExtractsDataArrayCorrectly(): void
    {
        $data = ['title' => 'Test', 'content' => 'Hello'];
        $request = CreateRequest::fromArray(['name' => 'test', 'data' => $data]);

        $this->assertSame($data, $request->data);
    }

    public function testFromArrayExtractsSchemaVersionWhenPresent(): void
    {
        $request = CreateRequest::fromArray([
            'name' => 'test',
            'data' => [],
            'schemaVersion' => '2.0.0',
        ]);

        $this->assertSame('2.0.0', $request->schemaVersion);
    }

    public function testFromArrayExtractsBranchWhenPresent(): void
    {
        $request = CreateRequest::fromArray([
            'name' => 'test',
            'data' => [],
            'branch' => 'develop',
        ]);

        $this->assertSame('develop', $request->branch);
    }

    public function testFromArrayExtractsScopeWhenPresent(): void
    {
        $request = CreateRequest::fromArray([
            'name' => 'test',
            'data' => [],
            'scope' => 'organization',
        ]);

        $this->assertSame('organization', $request->scope);
    }

    public function testFromArrayReturnsEmptyStringForMissingName(): void
    {
        $request = CreateRequest::fromArray(['data' => []]);

        $this->assertSame('', $request->name);
    }

    public function testFromArrayReturnsEmptyArrayForMissingData(): void
    {
        $request = CreateRequest::fromArray(['name' => 'test']);

        $this->assertSame([], $request->data);
    }

    public function testFromArrayReturnsNullForMissingOptionalFields(): void
    {
        $request = CreateRequest::fromArray(['name' => 'test', 'data' => []]);

        $this->assertNull($request->schemaVersion);
        $this->assertNull($request->branch);
        $this->assertNull($request->scope);
    }

    public function testFromArrayHandlesNonStringValuesForName(): void
    {
        $request = CreateRequest::fromArray(['name' => 123, 'data' => []]);

        $this->assertSame('', $request->name);
    }

    public function testFromArrayHandlesNonArrayValuesForData(): void
    {
        $request = CreateRequest::fromArray(['name' => 'test', 'data' => 'not-an-array']);

        $this->assertSame([], $request->data);
    }

    public function testFromArrayHandlesNonStringOptionalValues(): void
    {
        $request = CreateRequest::fromArray([
            'name' => 'test',
            'data' => [],
            'schemaVersion' => 123,
            'branch' => ['invalid'],
            'scope' => null,
        ]);

        $this->assertNull($request->schemaVersion);
        $this->assertNull($request->branch);
        $this->assertNull($request->scope);
    }
}
