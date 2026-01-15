<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\Response;

use Bareapi\Response\ErrorResponse;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ErrorResponseTest extends TestCase
{
    public function testCreateReturnsJsonResponseWithCorrectStatusCode(): void
    {
        $response = ErrorResponse::create(400, 'Bad Request');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(400, $response->getStatusCode());
    }

    public function testCreateIncludesErrorCodeMessageAndStatusInPayload(): void
    {
        $response = ErrorResponse::create(404, 'Not Found');

        $data = json_decode($response->getContent(), true);

        $this->assertSame(404, $data['error']);
        $this->assertSame('404', $data['code']);
        $this->assertSame('Not Found', $data['message']);
        $this->assertSame('error', $data['status']);
    }

    public function testCreateIncludesExceptionIdWhenProvided(): void
    {
        $response = ErrorResponse::create(500, 'Internal Error', null, 'metastore-abc123');

        $data = json_decode($response->getContent(), true);

        $this->assertSame('metastore-abc123', $data['exceptionId']);
    }

    public function testCreateOmitsExceptionIdWhenNull(): void
    {
        $response = ErrorResponse::create(400, 'Bad Request');

        $data = json_decode($response->getContent(), true);

        $this->assertArrayNotHasKey('exceptionId', $data);
    }

    public function testCreateIncludesErrorsArrayWhenProvided(): void
    {
        $errors = [
            [
                'path' => '/title',
                'message' => 'Title is required',
            ],
            [
                'message' => 'Invalid format',
            ],
        ];

        $response = ErrorResponse::create(422, 'Validation failed', $errors);

        $data = json_decode($response->getContent(), true);

        $this->assertSame($errors, $data['errors']);
    }

    public function testCreateSetsCorrectContentTypeHeader(): void
    {
        $response = ErrorResponse::create(400, 'Bad Request');

        $this->assertSame('application/vnd.api+json', $response->headers->get('Content-Type'));
    }

    public function testBadRequestReturns400WithDefaultMessage(): void
    {
        $response = ErrorResponse::badRequest();

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Bad Request', $data['message']);
    }

    public function testBadRequestReturns400WithCustomMessage(): void
    {
        $response = ErrorResponse::badRequest('Invalid JSON');

        $data = json_decode($response->getContent(), true);
        $this->assertSame('Invalid JSON', $data['message']);
    }

    public function testUnauthorizedReturns401WithExceptionId(): void
    {
        $response = ErrorResponse::unauthorized();

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Unauthorized', $data['message']);
        $this->assertArrayHasKey('exceptionId', $data);
        $this->assertMatchesRegularExpression('/^metastore-[a-f0-9]{16}$/', $data['exceptionId']);
    }

    public function testForbiddenReturns403WithExceptionId(): void
    {
        $response = ErrorResponse::forbidden();

        $this->assertSame(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Forbidden', $data['message']);
        $this->assertArrayHasKey('exceptionId', $data);
        $this->assertMatchesRegularExpression('/^metastore-[a-f0-9]{16}$/', $data['exceptionId']);
    }

    public function testNotFoundReturns404WithoutExceptionId(): void
    {
        $response = ErrorResponse::notFound();

        $this->assertSame(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Not Found', $data['message']);
        $this->assertArrayNotHasKey('exceptionId', $data);
    }

    public function testConflictReturns409WithoutExceptionId(): void
    {
        $response = ErrorResponse::conflict();

        $this->assertSame(409, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Conflict', $data['message']);
        $this->assertArrayNotHasKey('exceptionId', $data);
    }

    public function testValidationErrorReturns422WithErrorsArray(): void
    {
        $errors = [
            [
                'path' => '/title',
                'message' => 'Required field',
            ],
            [
                'message' => 'Must be a string',
            ],
        ];

        $response = ErrorResponse::validationError($errors);

        $this->assertSame(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Validation failed', $data['message']);
        $this->assertSame($errors, $data['errors']);
    }

    public function testValidationErrorFromRawHandlesStructuredErrorsWithErrorsKey(): void
    {
        $rawErrors = [
            'errors' => [
                [
                    'path' => '/name',
                    'message' => 'Name is required',
                ],
                [
                    'message' => 'Invalid data',
                ],
            ],
        ];

        $response = ErrorResponse::validationErrorFromRaw($rawErrors);

        $data = json_decode($response->getContent(), true);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertCount(2, $data['errors']);
        $this->assertSame('Name is required', $data['errors'][0]['message']);
    }

    public function testValidationErrorFromRawHandlesFlatArrayOfStringMessages(): void
    {
        $rawErrors = [
            'Field is required',
            'Invalid format',
        ];

        $response = ErrorResponse::validationErrorFromRaw($rawErrors);

        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['errors']);
        $this->assertSame('Field is required', $data['errors'][0]['message']);
        $this->assertSame('Invalid format', $data['errors'][1]['message']);
    }

    public function testValidationErrorFromRawHandlesArrayWithMessageObjects(): void
    {
        $rawErrors = [
            [
                'message' => 'First error',
                'path' => '/field1',
            ],
            [
                'message' => 'Second error',
            ],
        ];

        $response = ErrorResponse::validationErrorFromRaw($rawErrors);

        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['errors']);
        $this->assertSame('/field1', $data['errors'][0]['path']);
        $this->assertSame('First error', $data['errors'][0]['message']);
    }

    public function testValidationErrorFromRawHandlesStringErrorsInStructuredFormat(): void
    {
        $rawErrors = [
            'errors' => [
                'Title cannot be empty',
                'Name is required',
            ],
        ];

        $response = ErrorResponse::validationErrorFromRaw($rawErrors);

        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['errors']);
        $this->assertSame('Title cannot be empty', $data['errors'][0]['message']);
    }

    public function testInternalErrorReturns500WithExceptionId(): void
    {
        $response = ErrorResponse::internalError();

        $this->assertSame(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Internal Server Error', $data['message']);
        $this->assertArrayHasKey('exceptionId', $data);
        $this->assertMatchesRegularExpression('/^metastore-[a-f0-9]{16}$/', $data['exceptionId']);
    }

    public function testInternalErrorWithCustomMessage(): void
    {
        $response = ErrorResponse::internalError('Database connection failed');

        $data = json_decode($response->getContent(), true);
        $this->assertSame('Database connection failed', $data['message']);
    }
}
