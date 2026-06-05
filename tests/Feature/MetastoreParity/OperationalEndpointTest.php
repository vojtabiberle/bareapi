<?php

declare(strict_types=1);

namespace Bareapi\Tests\Feature\MetastoreParity;

use Bareapi\Tests\Feature\FeatureTestCase;

final class OperationalEndpointTest extends FeatureTestCase
{
    public function testHomeReturnsServiceIndex(): void
    {
        $this->client->request('GET', '/');

        $this->assertResponseStatusCodeSame(200);
        $content = $this->client->getResponse()->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('BareAPI', $content);
        $this->assertStringContainsString('/api/v1/repository/{objectType}', $content);
    }

    public function testHealthCheckReturnsDatabaseStatus(): void
    {
        $this->client->request('GET', '/health-check');

        $this->assertResponseStatusCodeSame(200);
        $content = $this->client->getResponse()->getContent();
        $response = json_decode(is_string($content) ? $content : '', true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);
        $this->assertSame('ok', $response['status'] ?? null);
        $this->assertSame('BareAPI', $response['service'] ?? null);
    }

    public function testDocumentationReturnsOpenApiDocument(): void
    {
        $this->client->request('GET', '/api/v1/documentation/openapi.json');

        $this->assertResponseStatusCodeSame(200);
        $content = $this->client->getResponse()->getContent();
        $response = json_decode(is_string($content) ? $content : '', true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);
        $this->assertSame('3.1.0', $response['openapi'] ?? null);
        $this->assertArrayHasKey('paths', $response);
        $this->assertIsArray($response['paths']);
        $this->assertArrayHasKey('/api/v1/repository/{objectType}', $response['paths']);
    }
}
