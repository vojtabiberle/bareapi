<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\DTO;

use Bareapi\DTO\MetaObjectResponse;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class MetaObjectResponseTest extends TestCase
{
    public function testConstructorStoresAllPropertiesCorrectly(): void
    {
        $now = new DateTimeImmutable();
        $response = new MetaObjectResponse(
            uuid: 'uuid-123',
            objectType: 'notes',
            schemaVersion: '1.0.0',
            branch: 'main',
            name: 'my-note',
            projectId: 123,
            organizationId: 'org-456',
            lastUpdated: $now,
            createdAt: $now,
            revision: 1,
            data: [
                'title' => 'Test',
            ],
            revisionCreatedAt: $now,
        );

        $this->assertSame('uuid-123', $response->uuid);
        $this->assertSame('notes', $response->objectType);
        $this->assertSame('1.0.0', $response->schemaVersion);
        $this->assertSame('main', $response->branch);
        $this->assertSame('my-note', $response->name);
        $this->assertSame(123, $response->projectId);
        $this->assertSame('org-456', $response->organizationId);
        $this->assertSame(1, $response->revision);
        $this->assertSame([
            'title' => 'Test',
        ], $response->data);
    }

    public function testFromArrayHandlesAllFieldsFromDatabaseRow(): void
    {
        $row = [
            'uuid' => 'db-uuid-789',
            'object_type' => 'articles',
            'schema_version' => '2.0.0',
            'branch' => 'feature',
            'name' => 'article-name',
            'project_id' => 456,
            'organization_id' => 'org-789',
            'last_updated' => '2024-01-15T10:30:00+00:00',
            'created_at' => '2024-01-10T08:00:00+00:00',
            'revision' => 5,
            'data' => [
                'title' => 'Article Title',
            ],
            'revision_created_at' => '2024-01-15T10:30:00+00:00',
        ];

        $response = MetaObjectResponse::fromArray($row);

        $this->assertSame('db-uuid-789', $response->uuid);
        $this->assertSame('articles', $response->objectType);
        $this->assertSame('2.0.0', $response->schemaVersion);
        $this->assertSame('feature', $response->branch);
        $this->assertSame('article-name', $response->name);
        $this->assertSame(456, $response->projectId);
        $this->assertSame('org-789', $response->organizationId);
        $this->assertSame(5, $response->revision);
        $this->assertSame([
            'title' => 'Article Title',
        ], $response->data);
    }

    public function testFromArrayHandlesJsonStringDataField(): void
    {
        $row = [
            'uuid' => 'test-uuid',
            'object_type' => 'notes',
            'data' => '{"title": "JSON String", "content": "Hello"}',
        ];

        $response = MetaObjectResponse::fromArray($row);

        $this->assertSame([
            'title' => 'JSON String',
            'content' => 'Hello',
        ], $response->data);
    }

    public function testFromArrayHandlesArrayDataField(): void
    {
        $data = [
            'key' => 'value',
            'nested' => [
                'a' => 1,
            ],
        ];
        $row = [
            'uuid' => 'test-uuid',
            'object_type' => 'notes',
            'data' => $data,
        ];

        $response = MetaObjectResponse::fromArray($row);

        $this->assertSame($data, $response->data);
    }

    public function testFromArrayReturnsDefaultsForMissingFields(): void
    {
        $row = [];

        $response = MetaObjectResponse::fromArray($row);

        $this->assertSame('', $response->uuid);
        $this->assertSame('', $response->objectType);
        $this->assertSame('', $response->schemaVersion);
        $this->assertSame('main', $response->branch);
        $this->assertSame('', $response->name);
        $this->assertNull($response->projectId);
        $this->assertSame('', $response->organizationId);
        $this->assertSame(1, $response->revision);
        $this->assertSame([], $response->data);
    }

    public function testFromArrayHandlesNumericProjectId(): void
    {
        $row = [
            'uuid' => 'test',
            'object_type' => 'notes',
            'project_id' => '789',
        ];

        $response = MetaObjectResponse::fromArray($row);

        $this->assertSame(789, $response->projectId);
    }

    public function testFromArrayHandlesNullProjectId(): void
    {
        $row = [
            'uuid' => 'test',
            'object_type' => 'notes',
            'project_id' => null,
        ];

        $response = MetaObjectResponse::fromArray($row);

        $this->assertNull($response->projectId);
    }

    public function testFromArrayParsesDateTimeImmutableInput(): void
    {
        $date = new DateTimeImmutable('2024-06-01T12:00:00+00:00');
        $row = [
            'uuid' => 'test',
            'object_type' => 'notes',
            'last_updated' => $date,
            'created_at' => $date,
        ];

        $response = MetaObjectResponse::fromArray($row);

        $this->assertEquals($date, $response->lastUpdated);
        $this->assertEquals($date, $response->createdAt);
    }

    public function testFromArrayParsesAtomFormatStrings(): void
    {
        $row = [
            'uuid' => 'test',
            'object_type' => 'notes',
            'last_updated' => '2024-03-20T15:45:30+00:00',
        ];

        $response = MetaObjectResponse::fromArray($row);

        $this->assertSame('2024-03-20T15:45:30+00:00', $response->lastUpdated->format(DateTimeImmutable::ATOM));
    }

    public function testFromArrayParsesDateTimeWithMicroseconds(): void
    {
        $row = [
            'uuid' => 'test',
            'object_type' => 'notes',
            'last_updated' => '2024-03-20 15:45:30.123456+00:00',
        ];

        $response = MetaObjectResponse::fromArray($row);

        $this->assertSame('2024', $response->lastUpdated->format('Y'));
        $this->assertSame('03', $response->lastUpdated->format('m'));
        $this->assertSame('20', $response->lastUpdated->format('d'));
    }

    public function testFromArrayParsesDateTimeWithoutMicroseconds(): void
    {
        $row = [
            'uuid' => 'test',
            'object_type' => 'notes',
            'last_updated' => '2024-03-20 15:45:30+00:00',
        ];

        $response = MetaObjectResponse::fromArray($row);

        $this->assertSame('2024', $response->lastUpdated->format('Y'));
    }

    public function testFromArrayReturnsCurrentTimeForInvalidInput(): void
    {
        $before = new DateTimeImmutable();
        $row = [
            'uuid' => 'test',
            'object_type' => 'notes',
            'last_updated' => 'invalid-date',
        ];

        $response = MetaObjectResponse::fromArray($row);
        $after = new DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $response->lastUpdated);
        $this->assertLessThanOrEqual($after, $response->lastUpdated);
    }

    public function testFromArrayUsesCreatedAtForRevisionCreatedAtWhenMissing(): void
    {
        $row = [
            'uuid' => 'test',
            'object_type' => 'notes',
            'created_at' => '2024-01-01T00:00:00+00:00',
        ];

        $response = MetaObjectResponse::fromArray($row);

        $this->assertSame(
            $response->createdAt->format(DateTimeImmutable::ATOM),
            $response->revisionCreatedAt->format(DateTimeImmutable::ATOM)
        );
    }

    public function testFromArrayHandlesInvalidJsonDataString(): void
    {
        $row = [
            'uuid' => 'test',
            'object_type' => 'notes',
            'data' => 'not-valid-json',
        ];

        $response = MetaObjectResponse::fromArray($row);

        $this->assertSame([], $response->data);
    }
}
