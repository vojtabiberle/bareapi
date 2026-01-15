<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\Logging;

use Bareapi\Logging\JsonFormatter;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class JsonFormatterTest extends TestCase
{
    private JsonFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new JsonFormatter();
    }

    public function testFormatOutputsJsonWithNewline(): void
    {
        $record = $this->createLogRecord('Test message');

        $result = $this->formatter->format($record);

        $this->assertStringEndsWith("\n", $result);
        $this->assertJson(trim($result));
    }

    public function testFormatIncludesTimestampInRfc3339ExtendedFormat(): void
    {
        $datetime = new DateTimeImmutable('2024-01-15T10:30:00.123456+00:00');
        $record = $this->createLogRecord('Test', datetime: $datetime);

        $result = $this->formatter->format($record);
        $data = json_decode(trim($result), true);

        $this->assertArrayHasKey('timestamp', $data);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}/', $data['timestamp']);
    }

    public function testFormatIncludesLowercaseLevel(): void
    {
        $record = $this->createLogRecord('Test', level: Level::Warning);

        $result = $this->formatter->format($record);
        $data = json_decode(trim($result), true);

        $this->assertSame('warning', $data['level']);
    }

    public function testFormatIncludesMessageAndChannel(): void
    {
        $record = $this->createLogRecord('My log message', channel: 'app');

        $result = $this->formatter->format($record);
        $data = json_decode(trim($result), true);

        $this->assertSame('My log message', $data['message']);
        $this->assertSame('app', $data['channel']);
    }

    public function testFormatIncludesContextWhenNotEmpty(): void
    {
        $context = [
            'user_id' => 123,
            'action' => 'login',
        ];
        $record = $this->createLogRecord('Test', context: $context);

        $result = $this->formatter->format($record);
        $data = json_decode(trim($result), true);

        $this->assertArrayHasKey('context', $data);
        $this->assertSame(123, $data['context']['user_id']);
        $this->assertSame('login', $data['context']['action']);
    }

    public function testFormatOmitsContextWhenEmpty(): void
    {
        $record = $this->createLogRecord('Test', context: []);

        $result = $this->formatter->format($record);
        $data = json_decode(trim($result), true);

        $this->assertArrayNotHasKey('context', $data);
    }

    public function testFormatIncludesExtraWhenNotEmpty(): void
    {
        $extra = [
            'request_id' => 'abc-123',
        ];
        $record = $this->createLogRecord('Test', extra: $extra);

        $result = $this->formatter->format($record);
        $data = json_decode(trim($result), true);

        $this->assertArrayHasKey('extra', $data);
        $this->assertSame('abc-123', $data['extra']['request_id']);
    }

    public function testFormatOmitsExtraWhenEmpty(): void
    {
        $record = $this->createLogRecord('Test', extra: []);

        $result = $this->formatter->format($record);
        $data = json_decode(trim($result), true);

        $this->assertArrayNotHasKey('extra', $data);
    }

    public function testNormalizeArrayConvertsThrowableToStructuredArray(): void
    {
        $exception = new \RuntimeException('Test error', 42);
        $record = $this->createLogRecord('Error occurred', context: [
            'exception' => $exception,
        ]);

        $result = $this->formatter->format($record);
        $data = json_decode(trim($result), true);

        $this->assertArrayHasKey('exception', $data['context']);
        $this->assertSame('RuntimeException', $data['context']['exception']['class']);
        $this->assertSame('Test error', $data['context']['exception']['message']);
        $this->assertSame(42, $data['context']['exception']['code']);
        $this->assertStringContainsString(':', $data['context']['exception']['file']);
    }

    public function testNormalizeArrayConvertsDateTimeInterfaceToRfc3339String(): void
    {
        $date = new DateTimeImmutable('2024-06-15T14:30:00+00:00');
        $record = $this->createLogRecord('Test', context: [
            'created_at' => $date,
        ]);

        $result = $this->formatter->format($record);
        $data = json_decode(trim($result), true);

        $this->assertSame('2024-06-15T14:30:00+00:00', $data['context']['created_at']);
    }

    public function testNormalizeArrayCallsToStringOnObjectsWithThatMethod(): void
    {
        $object = new class() {
            public function __toString(): string
            {
                return 'StringableObject';
            }
        };
        $record = $this->createLogRecord('Test', context: [
            'object' => $object,
        ]);

        $result = $this->formatter->format($record);
        $data = json_decode(trim($result), true);

        $this->assertSame('StringableObject', $data['context']['object']);
    }

    public function testNormalizeArrayCallsToArrayOnObjectsWithThatMethod(): void
    {
        $object = new class() {
            public function toArray(): array
            {
                return [
                    'key' => 'value',
                    'number' => 42,
                ];
            }
        };
        $record = $this->createLogRecord('Test', context: [
            'object' => $object,
        ]);

        $result = $this->formatter->format($record);
        $data = json_decode(trim($result), true);

        $this->assertSame([
            'key' => 'value',
            'number' => 42,
        ], $data['context']['object']);
    }

    public function testNormalizeArrayReturnsClassNameForOtherObjects(): void
    {
        $object = new \stdClass();
        $record = $this->createLogRecord('Test', context: [
            'object' => $object,
        ]);

        $result = $this->formatter->format($record);
        $data = json_decode(trim($result), true);

        $this->assertSame('stdClass', $data['context']['object']);
    }

    public function testNormalizeArrayRecursivelyNormalizesNestedArrays(): void
    {
        $date = new DateTimeImmutable('2024-01-01T00:00:00+00:00');
        $context = [
            'outer' => [
                'inner' => [
                    'date' => $date,
                    'value' => 'test',
                ],
            ],
        ];
        $record = $this->createLogRecord('Test', context: $context);

        $result = $this->formatter->format($record);
        $data = json_decode(trim($result), true);

        $this->assertSame('2024-01-01T00:00:00+00:00', $data['context']['outer']['inner']['date']);
        $this->assertSame('test', $data['context']['outer']['inner']['value']);
    }

    public function testFormatHandlesAllLogLevels(): void
    {
        $levels = [Level::Debug, Level::Info, Level::Notice, Level::Warning, Level::Error, Level::Critical, Level::Alert, Level::Emergency];

        foreach ($levels as $level) {
            $record = $this->createLogRecord('Test', level: $level);
            $result = $this->formatter->format($record);
            $data = json_decode(trim($result), true);

            $this->assertSame(strtolower($level->getName()), $data['level']);
        }
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $extra
     */
    private function createLogRecord(
        string $message,
        Level $level = Level::Info,
        string $channel = 'test',
        array $context = [],
        array $extra = [],
        ?DateTimeImmutable $datetime = null,
    ): LogRecord {
        return new LogRecord(
            datetime: $datetime ?? new DateTimeImmutable(),
            channel: $channel,
            level: $level,
            message: $message,
            context: $context,
            extra: $extra,
        );
    }
}
