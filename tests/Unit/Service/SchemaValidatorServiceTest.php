<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\Service;

use Bareapi\Exception\SchemaNotFoundException;
use Bareapi\Exception\ValidationException;
use Bareapi\Service\SchemaValidatorService;
use PHPUnit\Framework\TestCase;

final class SchemaValidatorServiceTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = dirname(__DIR__, 3);
    }

    public function testValidPayloadReturnsValidatedData(): void
    {
        $service = new SchemaValidatorService($this->projectDir);
        $type = 'notes';
        $payload = [
            'title' => 'Test Note',
            'content' => 'Hello',
        ];

        $result = $service->validate($type, $payload);

        $this->assertSame($payload['title'], $result['title']);
        $this->assertSame($payload['content'], $result['content']);
    }

    public function testInvalidPayloadThrowsValidationException(): void
    {
        $service = new SchemaValidatorService($this->projectDir);
        $type = 'notes';
        $payload = [
            'content' => 'No title provided',
        ]; // Missing required 'title'

        $this->expectException(ValidationException::class);

        try {
            $service->validate($type, $payload);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertNotEmpty($errors);
            $this->assertStringContainsString('title', implode(' ', $errors));
            throw $e;
        }
    }

    public function testMissingSchemaThrowsSchemaNotFoundException(): void
    {
        $service = new SchemaValidatorService($this->projectDir);
        $type = 'nonexistent';
        $payload = [
            'foo' => 'bar',
        ];

        $this->expectException(SchemaNotFoundException::class);

        $service->validate($type, $payload);
    }
}
