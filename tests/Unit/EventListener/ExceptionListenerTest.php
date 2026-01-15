<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\EventListener;

use Bareapi\EventListener\ExceptionListener;
use Bareapi\Exception\ForbiddenException;
use Bareapi\Exception\InvalidFilterException;
use Bareapi\Exception\MetaObjectNotFoundException;
use Bareapi\Exception\SchemaNotFoundException;
use Bareapi\Exception\UnauthorizedException;
use Bareapi\Exception\ValidationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ExceptionListenerTest extends TestCase
{
    private const CONTENT_TYPE = 'application/vnd.api+json';

    public function testUnauthorizedExceptionReturns401(): void
    {
        $listener = new ExceptionListener('test');
        $event = $this->createExceptionEvent(
            new UnauthorizedException('Custom unauthorized message'),
            '/api/v1/repository/notes'
        );

        $listener->onKernelException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(self::CONTENT_TYPE, $response->headers->get('Content-Type'));

        $data = json_decode($response->getContent(), true);
        $this->assertSame('401', $data['errors'][0]['status']);
        $this->assertSame('Unauthorized', $data['errors'][0]['title']);
        $this->assertSame('Custom unauthorized message', $data['errors'][0]['detail']);
    }

    public function testForbiddenExceptionReturns403(): void
    {
        $listener = new ExceptionListener('test');
        $event = $this->createExceptionEvent(
            new ForbiddenException('Access denied'),
            '/api/v1/repository/notes'
        );

        $listener->onKernelException($event);

        $response = $event->getResponse();
        $this->assertSame(403, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('403', $data['errors'][0]['status']);
        $this->assertSame('Forbidden', $data['errors'][0]['title']);
    }

    public function testMetaObjectNotFoundExceptionReturns404(): void
    {
        $listener = new ExceptionListener('test');
        $event = $this->createExceptionEvent(
            new MetaObjectNotFoundException('Object not found: abc-123'),
            '/api/v1/repository/notes/abc-123'
        );

        $listener->onKernelException($event);

        $response = $event->getResponse();
        $this->assertSame(404, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('404', $data['errors'][0]['status']);
        $this->assertSame('Not Found', $data['errors'][0]['title']);
        $this->assertSame('Object not found: abc-123', $data['errors'][0]['detail']);
    }

    public function testSchemaNotFoundExceptionReturns404(): void
    {
        $listener = new ExceptionListener('test');
        $event = $this->createExceptionEvent(
            new SchemaNotFoundException('Schema not found'),
            '/api/v1/repository/unknown'
        );

        $listener->onKernelException($event);

        $response = $event->getResponse();
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testValidationExceptionReturns422WithMultipleErrors(): void
    {
        $listener = new ExceptionListener('test');
        $errors = ['Title is required', 'Content must be a string'];
        $event = $this->createExceptionEvent(
            new ValidationException($errors),
            '/api/v1/repository/notes'
        );

        $listener->onKernelException($event);

        $response = $event->getResponse();
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(self::CONTENT_TYPE, $response->headers->get('Content-Type'));

        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['errors']);
        $this->assertSame('422', $data['errors'][0]['status']);
        $this->assertSame('Validation Error', $data['errors'][0]['title']);
        $this->assertSame('Title is required', $data['errors'][0]['detail']);
    }

    public function testInvalidFilterExceptionReturns400(): void
    {
        $listener = new ExceptionListener('test');
        $event = $this->createExceptionEvent(
            new InvalidFilterException('status', 'notes'),
            '/api/v1/repository/notes'
        );

        $listener->onKernelException($event);

        $response = $event->getResponse();
        $this->assertSame(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('400', $data['errors'][0]['status']);
        $this->assertSame('Bad Request', $data['errors'][0]['title']);
        $this->assertStringContainsString('status', $data['errors'][0]['detail']);
    }

    public function testGenericExceptionReturns500(): void
    {
        $listener = new ExceptionListener('test');
        $event = $this->createExceptionEvent(
            new \RuntimeException('Something went wrong'),
            '/api/v1/repository/notes'
        );

        $listener->onKernelException($event);

        $response = $event->getResponse();
        $this->assertSame(500, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('500', $data['errors'][0]['status']);
        $this->assertSame('Internal Server Error', $data['errors'][0]['title']);
    }

    public function testNonApiRoutesNotIntercepted(): void
    {
        $listener = new ExceptionListener('test');
        $event = $this->createExceptionEvent(
            new \RuntimeException('Error'),
            '/homepage'
        );

        $listener->onKernelException($event);

        // Response should NOT be set for non-API routes
        $this->assertNull($event->getResponse());
    }

    public function testErrorResponseHasCorrectContentType(): void
    {
        $listener = new ExceptionListener('test');
        $event = $this->createExceptionEvent(
            new ForbiddenException('Forbidden'),
            '/api/v1/repository/notes'
        );

        $listener->onKernelException($event);

        $response = $event->getResponse();
        $this->assertSame(self::CONTENT_TYPE, $response->headers->get('Content-Type'));
    }

    public function testDevEnvironmentShowsDebugInfo(): void
    {
        $listener = new ExceptionListener('dev');
        $exception = new \RuntimeException('Test error');
        $event = $this->createExceptionEvent($exception, '/api/v1/repository/notes');

        $listener->onKernelException($event);

        $response = $event->getResponse();
        $data = json_decode($response->getContent(), true);

        // In dev, should show exception class and file info
        $this->assertStringContainsString('RuntimeException', $data['errors'][0]['detail']);
        $this->assertStringContainsString('Test error', $data['errors'][0]['detail']);
    }

    public function testProdEnvironmentHidesDebugInfo(): void
    {
        $listener = new ExceptionListener('prod');
        $exception = new \RuntimeException('Sensitive internal error');
        $event = $this->createExceptionEvent($exception, '/api/v1/repository/notes');

        $listener->onKernelException($event);

        $response = $event->getResponse();
        $data = json_decode($response->getContent(), true);

        // In prod, should show generic message for 500 errors
        $this->assertSame('An internal server error occurred.', $data['errors'][0]['detail']);
        $this->assertStringNotContainsString('Sensitive', $data['errors'][0]['detail']);
    }

    public function testHttpExceptionUsesCorrectStatusCode(): void
    {
        $listener = new ExceptionListener('test');
        $event = $this->createExceptionEvent(
            new NotFoundHttpException('Page not found'),
            '/api/v1/repository/notes'
        );

        $listener->onKernelException($event);

        $response = $event->getResponse();
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testValidationExceptionHandlesArrayErrors(): void
    {
        $listener = new ExceptionListener('test');
        $errors = [
            ['path' => '/title', 'message' => 'Required'],
        ];
        $event = $this->createExceptionEvent(
            new ValidationException($errors),
            '/api/v1/repository/notes'
        );

        $listener->onKernelException($event);

        $response = $event->getResponse();
        $this->assertSame(422, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['errors']);
    }

    private function createExceptionEvent(\Throwable $exception, string $path): ExceptionEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create($path);

        return new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);
    }
}
