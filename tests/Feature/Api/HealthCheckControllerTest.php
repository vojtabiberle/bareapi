<?php

declare(strict_types=1);

namespace Bareapi\Tests\Feature\Api;

use Bareapi\Tests\Feature\FeatureTestCase;

class HealthCheckControllerTest extends FeatureTestCase
{
    // ==================== Main Health Check Tests ====================

    public function testHealthCheckReturnsHealthyWhenDatabaseUp(): void
    {
        $this->client->request('GET', '/health-check');

        $this->assertResponseIsSuccessful();
        $data = $this->getJsonResponse();
        $this->assertSame('healthy', $data['status']);
    }

    public function testHealthCheckIncludesDatabaseStatus(): void
    {
        $this->client->request('GET', '/health-check');

        $this->assertResponseIsSuccessful();
        $data = $this->getJsonResponse();
        $this->assertArrayHasKey('checks', $data);
        $checks = $data['checks'];
        \assert(\is_array($checks));
        $this->assertArrayHasKey('database', $checks);
        $this->assertSame('ok', $checks['database']);
    }

    public function testHealthCheckDoesNotRequireAuthentication(): void
    {
        // No auth headers
        $this->client->request('GET', '/health-check');

        $this->assertResponseIsSuccessful();
    }

    // ==================== Liveness Probe Tests ====================

    public function testLivenessAlwaysReturnsOk(): void
    {
        $this->client->request('GET', '/health-check/liveness');

        $this->assertResponseIsSuccessful();
        $data = $this->getJsonResponse();
        $this->assertSame('ok', $data['status']);
    }

    public function testLivenessDoesNotRequireAuthentication(): void
    {
        // No auth headers
        $this->client->request('GET', '/health-check/liveness');

        $this->assertResponseIsSuccessful();
    }

    // ==================== Readiness Probe Tests ====================

    public function testReadinessReturnsReadyWhenDatabaseUp(): void
    {
        $this->client->request('GET', '/health-check/readiness');

        $this->assertResponseIsSuccessful();
        $data = $this->getJsonResponse();
        $this->assertSame('ready', $data['status']);
    }

    public function testReadinessDoesNotRequireAuthentication(): void
    {
        // No auth headers
        $this->client->request('GET', '/health-check/readiness');

        $this->assertResponseIsSuccessful();
    }

    /**
     * @return array<string, mixed>
     */
    private function getJsonResponse(): array
    {
        $content = $this->client->getResponse()->getContent();
        if ($content === false) {
            return [];
        }
        $decoded = json_decode($content, true);

        /** @var array<string, mixed> */
        return \is_array($decoded) ? $decoded : [];
    }
}
