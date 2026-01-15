<?php

declare(strict_types=1);

namespace Bareapi\Logging;

use Monolog\Formatter\JsonFormatter as MonologJsonFormatter;
use Monolog\LogRecord;

class JsonFormatter extends MonologJsonFormatter
{
    public function __construct()
    {
        parent::__construct(self::BATCH_MODE_JSON, true);
    }

    public function format(LogRecord $record): string
    {
        $normalized = $this->normalizeRecord($record);

        $output = [
            'timestamp' => $normalized['datetime'],
            'level' => strtolower($record->level->getName()),
            'message' => $normalized['message'],
            'channel' => $normalized['channel'],
        ];

        if ($normalized['context'] !== []) {
            $output['context'] = $normalized['context'];
        }

        if ($normalized['extra'] !== []) {
            $output['extra'] = $normalized['extra'];
        }

        return $this->toJson($output) . "\n";
    }

    /**
     * @return array{datetime: string, channel: string, level_name: string, message: string, context: array<string, mixed>, extra: array<string, mixed>}
     */
    protected function normalizeRecord(LogRecord $record): array
    {
        /** @var array<string, mixed> $context */
        $context = $record->context;
        /** @var array<string, mixed> $extra */
        $extra = $record->extra;

        return [
            'datetime' => $record->datetime->format(\DateTimeInterface::RFC3339_EXTENDED),
            'channel' => $record->channel,
            'level_name' => $record->level->getName(),
            'message' => $record->message,
            'context' => $this->normalizeArray($context),
            'extra' => $this->normalizeArray($extra),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeArray(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            if ($value instanceof \Throwable) {
                $normalized[$key] = [
                    'class' => $value::class,
                    'message' => $value->getMessage(),
                    'code' => $value->getCode(),
                    'file' => $value->getFile() . ':' . $value->getLine(),
                ];
            } elseif ($value instanceof \DateTimeInterface) {
                $normalized[$key] = $value->format(\DateTimeInterface::RFC3339);
            } elseif (is_object($value)) {
                if (method_exists($value, '__toString')) {
                    $normalized[$key] = (string) $value;
                } elseif (method_exists($value, 'toArray')) {
                    /** @var mixed $arrayValue */
                    $arrayValue = $value->toArray();
                    $normalized[$key] = $arrayValue;
                } else {
                    $normalized[$key] = $value::class;
                }
            } elseif (is_array($value)) {
                /** @var array<string, mixed> $value */
                $normalized[$key] = $this->normalizeArray($value);
            } else {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
