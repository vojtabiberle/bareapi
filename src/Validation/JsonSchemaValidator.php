<?php

declare(strict_types=1);

namespace Bareapi\Validation;

use Bareapi\Exception\ValidationException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

class JsonSchemaValidator
{
    private Validator $validator;

    public function __construct()
    {
        $this->validator = new Validator();
        $this->validator->setMaxErrors(10);
    }

    /**
     * Validate data against a JSON schema.
     *
     * @param array<string, mixed> $data The data to validate
     * @param array<string, mixed> $schema The JSON schema
     * @return array<string, mixed> The validated data (potentially with defaults applied)
     * @throws ValidationException If validation fails
     */
    public function validate(array $data, array $schema): array
    {
        $dataObject = $this->arrayToObject($data);
        /** @var \stdClass $schemaObject */
        $schemaObject = $this->arrayToObject($schema);

        $result = $this->validator->validate($dataObject, $schemaObject);

        if (! $result->isValid()) {
            $error = $result->error();
            if ($error === null) {
                throw new ValidationException([
                    'errors' => ['Unknown validation error'],
                ]);
            }

            $formatter = new ErrorFormatter();
            /** @var array<string, mixed> $formattedErrors */
            $formattedErrors = $formatter->format($error);
            $errors = $this->flattenErrors($formattedErrors);

            throw new ValidationException([
                'errors' => $errors,
            ]);
        }

        return $data;
    }

    /**
     * Validate data with schema path reference (for $ref resolution).
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $schema
     * @param string $schemaId Unique identifier for the schema (used for $ref resolution)
     * @return array<string, mixed>
     * @throws ValidationException
     */
    public function validateWithSchemaId(array $data, array $schema, string $schemaId): array
    {
        $schemaWithId = array_merge([
            '$id' => $schemaId,
        ], $schema);

        return $this->validate($data, $schemaWithId);
    }

    /**
     * Check if data is valid against schema without throwing.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $schema
     */
    public function isValid(array $data, array $schema): bool
    {
        try {
            $this->validate($data, $schema);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    /**
     * Convert array to stdClass object (required by opis/json-schema).
     */
    private function arrayToObject(mixed $data): mixed
    {
        if (is_array($data)) {
            if ($data === []) {
                return new \stdClass();
            }

            if (array_is_list($data)) {
                return array_map(fn ($item) => $this->arrayToObject($item), $data);
            }

            $object = new \stdClass();
            foreach ($data as $key => $value) {
                $object->{$key} = $this->arrayToObject($value);
            }

            return $object;
        }

        return $data;
    }

    /**
     * Flatten nested error structure into simple array of strings.
     *
     * @param array<string, mixed> $errors
     * @return array<int, string>
     */
    private function flattenErrors(array $errors, string $prefix = ''): array
    {
        $flattened = [];

        foreach ($errors as $path => $messages) {
            $fullPath = $prefix !== '' ? "{$prefix}.{$path}" : (string) $path;

            if (is_array($messages)) {
                if ($this->isListOfStrings($messages)) {
                    foreach ($messages as $message) {
                        if (is_string($message)) {
                            $flattened[] = $fullPath !== '' ? "[{$fullPath}] {$message}" : $message;
                        }
                    }
                } else {
                    /** @var array<string, mixed> $nestedMessages */
                    $nestedMessages = $messages;
                    $nested = $this->flattenErrors($nestedMessages, $fullPath);
                    $flattened = array_merge($flattened, $nested);
                }
            } elseif (is_string($messages)) {
                $flattened[] = $fullPath !== '' ? "[{$fullPath}] {$messages}" : $messages;
            }
        }

        return $flattened;
    }

    /**
     * Check if array is a list of strings.
     *
     * @param array<mixed> $arr
     */
    private function isListOfStrings(array $arr): bool
    {
        if (! array_is_list($arr)) {
            return false;
        }

        foreach ($arr as $item) {
            if (! is_string($item)) {
                return false;
            }
        }

        return true;
    }
}
