<?php

declare(strict_types=1);

namespace Bareapi\Response;

use Symfony\Component\HttpFoundation\JsonResponse;

class ErrorResponse
{
    private const CONTENT_TYPE = 'application/vnd.api+json';

    /**
     * @param array<int, array{path?: string, message: string, code?: string}>|null $errors
     */
    public static function create(
        int $statusCode,
        string $message,
        ?array $errors = null,
        ?string $exceptionId = null,
    ): JsonResponse {
        $payload = [
            'error' => $statusCode,
            'code' => (string) $statusCode,
            'message' => $message,
            'status' => 'error',
        ];

        if ($exceptionId !== null) {
            $payload['exceptionId'] = $exceptionId;
        }

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return new JsonResponse($payload, $statusCode, [
            'Content-Type' => self::CONTENT_TYPE,
        ]);
    }

    public static function badRequest(string $message = 'Bad Request'): JsonResponse
    {
        return self::create(400, $message);
    }

    public static function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return self::create(401, $message, null, self::generateExceptionId());
    }

    public static function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return self::create(403, $message, null, self::generateExceptionId());
    }

    public static function notFound(string $message = 'Not Found'): JsonResponse
    {
        return self::create(404, $message);
    }

    /**
     * @param array<int, array{path?: string, message: string, code?: string}> $errors
     */
    public static function validationError(array $errors): JsonResponse
    {
        return self::create(422, 'Validation failed', $errors);
    }

    public static function internalError(string $message = 'Internal Server Error'): JsonResponse
    {
        return self::create(500, $message, null, self::generateExceptionId());
    }

    private static function generateExceptionId(): string
    {
        return 'metastore-' . bin2hex(random_bytes(8));
    }
}
