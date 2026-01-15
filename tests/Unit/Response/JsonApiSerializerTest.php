<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\Response;

use Bareapi\DTO\MetaObjectResponse;
use Bareapi\Response\JsonApiSerializer;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class JsonApiSerializerTest extends TestCase
{
    private RequestStack&MockObject $requestStack;
    private JsonApiSerializer $serializer;

    protected function setUp(): void
    {
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->serializer = new JsonApiSerializer($this->requestStack);
    }

    public function testSuccessReturnsJsonResponseWith200StatusByDefault(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);
        $response = $this->createTestResponse();

        $result = $this->serializer->success($response);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertSame(200, $result->getStatusCode());
    }

    public function testSuccessReturnsJsonResponseWithCustomStatusCode(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);
        $response = $this->createTestResponse();

        $result = $this->serializer->success($response, 201);

        $this->assertSame(201, $result->getStatusCode());
    }

    public function testSuccessSetsCorrectContentTypeHeader(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);
        $response = $this->createTestResponse();

        $result = $this->serializer->success($response);

        $this->assertSame('application/vnd.api+json', $result->headers->get('Content-Type'));
    }

    public function testSuccessSerializesSingleMetaObjectResponseCorrectly(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);
        $response = $this->createTestResponse();

        $result = $this->serializer->success($response);
        $data = json_decode($result->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('type', $data['data']);
        $this->assertArrayHasKey('id', $data['data']);
        $this->assertArrayHasKey('attributes', $data['data']);
    }

    public function testSuccessSerializesArrayOfMetaObjectResponseCorrectly(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);
        $responses = [
            $this->createTestResponse('uuid-1'),
            $this->createTestResponse('uuid-2'),
        ];

        $result = $this->serializer->success($responses);
        $data = json_decode($result->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertIsArray($data['data']);
        $this->assertCount(2, $data['data']);
        $this->assertSame('uuid-1', $data['data'][0]['id']);
        $this->assertSame('uuid-2', $data['data'][1]['id']);
    }

    public function testSuccessIncludesTypeIdAttributesInResponse(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);
        $response = $this->createTestResponse('test-uuid', 'notes', '1.0.0', 'my-note');

        $result = $this->serializer->success($response);
        $data = json_decode($result->getContent(), true);

        $this->assertSame('notes', $data['data']['type']);
        $this->assertSame('test-uuid', $data['data']['id']);
        $this->assertSame('my-note', $data['data']['attributes']['name']);
        $this->assertSame('1.0.0', $data['data']['attributes']['schemaVersion']);
    }

    public function testSuccessIncludesSelfLinkWithCorrectUrl(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getScheme')->willReturn('https');
        $request->method('getHttpHost')->willReturn('api.example.com');
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $response = $this->createTestResponse('abc-123', 'notes');

        $result = $this->serializer->success($response);
        $data = json_decode($result->getContent(), true);

        $this->assertSame('https://api.example.com/api/v1/repository/notes/abc-123', $data['data']['links']['self']);
    }

    public function testSuccessIncludesSchemaRelationship(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);
        $response = $this->createTestResponse('uuid', 'notes', '2.0.0');

        $result = $this->serializer->success($response);
        $data = json_decode($result->getContent(), true);

        $this->assertArrayHasKey('relationships', $data['data']);
        $this->assertArrayHasKey('schema', $data['data']['relationships']);
        $this->assertSame('schemas', $data['data']['relationships']['schema']['data']['type']);
        $this->assertSame('notes-2.0.0', $data['data']['relationships']['schema']['data']['id']);
    }

    public function testSuccessIncludesRevisionsRelationship(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);
        $response = $this->createTestResponse(revision: 5);

        $result = $this->serializer->success($response);
        $data = json_decode($result->getContent(), true);

        $this->assertArrayHasKey('revisions', $data['data']['relationships']);
        $this->assertSame('revisions', $data['data']['relationships']['revisions']['data']['type']);
        $this->assertSame('5', $data['data']['relationships']['revisions']['data']['id']);
    }

    public function testSuccessIncludesProjectRelationshipOnlyWhenProjectIdExists(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);
        $response = $this->createTestResponse(projectId: 123);

        $result = $this->serializer->success($response);
        $data = json_decode($result->getContent(), true);

        $this->assertArrayHasKey('project', $data['data']['relationships']);
        $this->assertSame('projects', $data['data']['relationships']['project']['data']['type']);
        $this->assertSame('123', $data['data']['relationships']['project']['data']['id']);
    }

    public function testSuccessOmitsProjectRelationshipWhenProjectIdIsNull(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);
        $response = $this->createTestResponse(projectId: null);

        $result = $this->serializer->success($response);
        $data = json_decode($result->getContent(), true);

        $this->assertArrayNotHasKey('project', $data['data']['relationships']);
    }

    public function testSuccessFormatsDateTimesAsRfc3339(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);
        $timestamp = new DateTimeImmutable('2024-01-15T10:30:00+00:00');
        $response = $this->createTestResponse(
            lastUpdated: $timestamp,
            createdAt: $timestamp,
            revisionCreatedAt: $timestamp
        );

        $result = $this->serializer->success($response);
        $data = json_decode($result->getContent(), true);

        $this->assertSame('2024-01-15T10:30:00+00:00', $data['data']['attributes']['lastUpdated']);
        $this->assertSame('2024-01-15T10:30:00+00:00', $data['data']['attributes']['createdAt']);
        $this->assertSame('2024-01-15T10:30:00+00:00', $data['data']['attributes']['revisionCreatedAt']);
    }

    public function testCreatedReturnsJsonResponseWith201Status(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);
        $response = $this->createTestResponse();

        $result = $this->serializer->created($response);

        $this->assertSame(201, $result->getStatusCode());
    }

    public function testGetBaseUrlReturnsEmptyStringWhenNoRequest(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);
        $response = $this->createTestResponse('uuid', 'notes');

        $result = $this->serializer->success($response);
        $data = json_decode($result->getContent(), true);

        $this->assertSame('/api/v1/repository/notes/uuid', $data['data']['links']['self']);
    }

    public function testSuccessIncludesDataAttribute(): void
    {
        $this->requestStack->method('getCurrentRequest')->willReturn(null);
        $responseData = ['title' => 'Test Note', 'content' => 'Hello World'];
        $response = $this->createTestResponse(data: $responseData);

        $result = $this->serializer->success($response);
        $decoded = json_decode($result->getContent(), true);

        $this->assertSame($responseData, $decoded['data']['attributes']['data']);
    }

    private function createTestResponse(
        string $uuid = 'test-uuid-123',
        string $objectType = 'notes',
        string $schemaVersion = '1.0.0',
        string $name = 'test-object',
        ?int $projectId = 123,
        string $organizationId = 'org-123',
        int $revision = 1,
        array $data = ['title' => 'Test'],
        ?DateTimeImmutable $lastUpdated = null,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $revisionCreatedAt = null,
    ): MetaObjectResponse {
        $now = new DateTimeImmutable();

        return new MetaObjectResponse(
            uuid: $uuid,
            objectType: $objectType,
            schemaVersion: $schemaVersion,
            branch: 'main',
            name: $name,
            projectId: $projectId,
            organizationId: $organizationId,
            lastUpdated: $lastUpdated ?? $now,
            createdAt: $createdAt ?? $now,
            revision: $revision,
            data: $data,
            revisionCreatedAt: $revisionCreatedAt ?? $now,
        );
    }
}
