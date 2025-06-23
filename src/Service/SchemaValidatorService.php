<?php

declare(strict_types=1);

namespace Bareapi\Service;

use Bareapi\Exception\SchemaNotFoundException;
use Bareapi\Exception\ValidationException;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator as JsonSchemaValidator;

final class SchemaValidatorService
{
    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     * @throws ValidationException
     * @throws SchemaNotFoundException
     */
    public function validate(string $type, array $payload): array
    {
        $schemaPath = $this->projectDir . '/config/schemas/' . $type . '.json';
        if (! file_exists($schemaPath)) {
            throw new SchemaNotFoundException($type);
        }

        $schemaData = json_decode((string) file_get_contents($schemaPath));
        if ($schemaData === null) {
            throw new SchemaNotFoundException($type);
        }

        $validator = new JsonSchemaValidator();
        // JSON Schema expects objects, not arrays
        $payloadObj = json_decode(json_encode($payload) ?: '{}');

        $validator->validate($payloadObj, $schemaData, Constraint::CHECK_MODE_APPLY_DEFAULTS);

        if (! $validator->isValid()) {
            $errors = [];
            $errorList = $validator->getErrors();
            if (! is_iterable($errorList)) {
                throw new ValidationException([
                    'errors' => ['Unknown validation error'],
                ]);
            }
            foreach ($errorList as $error) {
                if (is_array($error)) {
                    $property = isset($error['property']) && is_string($error['property']) ? $error['property'] : '';
                    $message = isset($error['message']) && is_string($error['message']) ? $error['message'] : '';
                    $errors[] = ($property !== '' ? "[{$property}] " : '') . $message;
                }
            }
            $errorArray = [
                'errors' => $errors,
            ];
            throw new ValidationException($errorArray);
        }

        /** @var array<string, mixed> $result */
        $result = json_decode(json_encode($payloadObj) ?: '{}', true);
        return $result;
    }
}
