<?php

declare(strict_types=1);

namespace Bareapi\Tests\Feature\MetastoreParity;

use Bareapi\Tests\Feature\FeatureTestCase;

final class JsonApiResponseTest extends FeatureTestCase
{
    public function testRepositoryCreateReturnsJsonApiDocument(): void
    {
        $payload = [
            'name' => 'Test Tag',
            'branch' => 'main',
            'schemaVersion' => '1.0.0',
            'data' => [
                'id' => '018f3f8e-86d4-73a8-b79f-9a50f9c49b8a',
                'name' => 'my-tag',
                'color' => '#FF0000',
                'creator' => [
                    'id' => '018f3f8e-86d4-73a8-b79f-9a50f9c49b8b',
                    'name' => 'John Doe',
                ],
            ],
        ];

        $this->client->request(
            'POST',
            '/api/v1/repository/tags',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR)
        );

        $this->assertResponseStatusCodeSame(201);

        $content = $this->client->getResponse()->getContent();
        $response = json_decode(is_string($content) ? $content : '', true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('data', $response);
        $this->assertIsArray($response['data']);
        $this->assertSame('tags', $response['data']['type']);
        $this->assertIsString($response['data']['id']);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $response['data']['id']
        );

        $this->assertArrayHasKey('meta', $response['data']);
        $this->assertIsArray($response['data']['meta']);
        $this->assertSame([
            'schemaVersion' => '1.0.0',
            'branch' => 'main',
            'name' => 'Test Tag',
            'revision' => 1,
        ], array_intersect_key($response['data']['meta'], [
            'schemaVersion' => true,
            'branch' => true,
            'name' => true,
            'revision' => true,
        ]));

        $this->assertArrayHasKey('createdAt', $response['data']['meta']);
        $this->assertArrayHasKey('lastUpdated', $response['data']['meta']);
        $this->assertArrayHasKey('revisionCreatedAt', $response['data']['meta']);
        $this->assertSame($payload['data'], $response['data']['attributes']);
    }
}
