<?php

declare(strict_types=1);

namespace Bareapi\EventListener;

use Bareapi\Exception\ForbiddenException;
use Bareapi\Exception\InvalidFilterException;
use Bareapi\Exception\MetaObjectNotFoundException;
use Bareapi\Exception\SchemaNotFoundException;
use Bareapi\Exception\UnauthorizedException;
use Bareapi\Exception\ValidationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ExceptionListener
{
    private const JSON_API_CONTENT_TYPE = 'application/vnd.api+json';

    public function __construct(
        private string $kernelEnvironment,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (! str_starts_with($path, '/api/')) {
            // Not an API route, let default exception handling proceed
            return;
        }

        $exception = $event->getThrowable();

        // Handle specific exception types
        if ($exception instanceof UnauthorizedException) {
            $event->setResponse($this->createJsonApiError(
                '401',
                'Unauthorized',
                $exception->getMessage(),
                401
            ));

            return;
        }

        if ($exception instanceof ForbiddenException) {
            $event->setResponse($this->createJsonApiError(
                '403',
                'Forbidden',
                $exception->getMessage(),
                403
            ));

            return;
        }

        if ($exception instanceof MetaObjectNotFoundException || $exception instanceof SchemaNotFoundException) {
            $event->setResponse($this->createJsonApiError(
                '404',
                'Not Found',
                $exception->getMessage(),
                404
            ));

            return;
        }

        if ($exception instanceof ValidationException) {
            $errors = $exception->getErrors();
            $errorItems = [];

            foreach ($errors as $error) {
                $errorItems[] = [
                    'status' => '422',
                    'title' => 'Validation Error',
                    'detail' => is_string($error) ? $error : json_encode($error),
                ];
            }

            $event->setResponse(new JsonResponse([
                'errors' => $errorItems,
            ], 422, [
                'Content-Type' => self::JSON_API_CONTENT_TYPE,
            ]));

            return;
        }

        if ($exception instanceof InvalidFilterException) {
            $event->setResponse($this->createJsonApiError(
                '400',
                'Bad Request',
                $exception->getMessage(),
                400
            ));

            return;
        }

        // Handle HTTP exceptions
        $statusCode = 500;
        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
        }

        // Build message based on environment
        if ($this->kernelEnvironment === 'dev' || $this->kernelEnvironment === 'test') {
            $message = sprintf(
                '%s: %s in %s:%d',
                $exception::class,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            );
        } else {
            $message = $statusCode === 500
                ? 'An internal server error occurred.'
                : $exception->getMessage();
        }

        $event->setResponse($this->createJsonApiError(
            (string) $statusCode,
            $this->getStatusTitle($statusCode),
            $message,
            $statusCode
        ));
    }

    private function createJsonApiError(string $status, string $title, string $detail, int $httpStatus): JsonResponse
    {
        return new JsonResponse([
            'errors' => [[
                'status' => $status,
                'title' => $title,
                'detail' => $detail,
            ]],
        ], $httpStatus, [
            'Content-Type' => self::JSON_API_CONTENT_TYPE,
        ]);
    }

    private function getStatusTitle(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            500 => 'Internal Server Error',
            503 => 'Service Unavailable',
            default => 'Error',
        };
    }
}
