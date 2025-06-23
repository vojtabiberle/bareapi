<?php

declare(strict_types=1);

namespace Bareapi\EventListener;

use Bareapi\Exception\InvalidFilterException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ExceptionListener
{
    private string $kernelEnvironment;

    public function __construct(string $kernelEnvironment)
    {
        $this->kernelEnvironment = $kernelEnvironment;
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (strpos($path, '/api/') !== 0) {
            // Not an API route, let default exception handling proceed
            return;
        }

        $exception = $event->getThrowable();

        if ($exception instanceof InvalidFilterException) {
            $payload = [
                'status' => 'error',
                'code' => 400,
                'message' => $exception->getMessage(),
            ];
            $event->setResponse(new JsonResponse($payload, 400));
            return;
        }

        $statusCode = 500;

        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
        }

        if ($this->kernelEnvironment === 'dev' || $this->kernelEnvironment === 'test') {
            $message = sprintf(
                '%s: %s in %s:%d%sStack trace:%s%s',
                get_class($exception),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                PHP_EOL,
                PHP_EOL,
                $exception->getTraceAsString()
            );
        } else {
            $message = 'An internal server error occurred.';
        }

        $payload = [
            'status' => 'error',
            'code' => $statusCode,
            'message' => $message,
        ];

        $response = new JsonResponse($payload, $statusCode);
        $event->setResponse($response);
    }
}
