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
        $this->assertArrayNotHasKey('error', $response);
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

    public function testRepositoryDocumentationDeclaresTemplatedPathParameters(): void
    {
        $this->client->request('GET', '/api/v1/documentation/openapi.json');

        $this->assertResponseStatusCodeSame(200);
        $content = $this->client->getResponse()->getContent();
        $response = json_decode(is_string($content) ? $content : '', true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('paths', $response);
        $this->assertIsArray($response['paths']);

        $this->assertPathParameters($response['paths'], '/api/v1/repository/{objectType}/{id}', [
            'objectType' => 'string',
            'id' => 'string',
        ]);
        $this->assertPathParameters($response['paths'], '/api/v1/repository/{objectType}/{id}/revisions/{revision}', [
            'objectType' => 'string',
            'id' => 'string',
            'revision' => 'integer',
        ]);
    }

    /**
     * @param array<mixed> $paths
     * @param array<string, string> $expected
     */
    private function assertPathParameters(array $paths, string $path, array $expected): void
    {
        $this->assertArrayHasKey($path, $paths);
        $this->assertIsArray($paths[$path]);
        $this->assertArrayHasKey('parameters', $paths[$path]);
        $this->assertIsArray($paths[$path]['parameters']);

        $actual = array_reduce(
            $paths[$path]['parameters'],
            static function (array $parameters, mixed $parameter): array {
                if (! is_array($parameter)) {
                    return $parameters;
                }
                if (($parameter['in'] ?? null) !== 'path' || ! is_string($parameter['name'] ?? null)) {
                    return $parameters;
                }

                $name = $parameter['name'];
                $parameters[$name] = [
                    'required' => $parameter['required'] ?? null,
                    'type' => is_array($parameter['schema'] ?? null) ? ($parameter['schema']['type'] ?? null) : null,
                ];

                return $parameters;
            },
            []
        );

        foreach ($expected as $name => $type) {
            $this->assertSame([
                'required' => true,
                'type' => $type,
            ], $actual[$name] ?? null);
        }
    }
}
