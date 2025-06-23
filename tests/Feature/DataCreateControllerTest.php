<?php

namespace Bareapi\Tests\Feature;

class DataCreateControllerTest extends FeatureTestCase
{
    public function testAuthenticatedUserCanCreateNote(): void
    {
        $payload = [
            'title' => 'Meeting Notes',
            'content' => 'Discuss project milestones and deadlines.',
        ];

        $this->client->request(
            'POST',
            '/api/notes',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            is_string(json_encode($payload)) ? json_encode($payload) : null
        );

        $this->assertResponseStatusCodeSame(201);
        $content = $this->client->getResponse()->getContent();
        $response = json_decode(is_string($content) ? $content : '', true);
        $this->assertIsArray($response, 'Response is not a valid array');
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('data', $response);
        $this->assertIsArray($response['data'], 'Data is not a valid array');
        $this->assertSame('Meeting Notes', $response['data']['title']);
        $this->assertSame('Discuss project milestones and deadlines.', $response['data']['content']);
    }

    public function testValidationErrorWhenTitleIsMissing(): void
    {
        $payload = [
            'content' => 'No title provided',
        ];

        $this->client->request(
            'POST',
            '/api/notes',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            is_string(json_encode($payload)) ? json_encode($payload) : null
        );

        $this->assertResponseStatusCodeSame(422);
        $content = $this->client->getResponse()->getContent();
        $response = json_decode(is_string($content) ? $content : '', true);
        $this->assertIsArray($response, 'Response is not a valid array');
        $this->assertArrayHasKey('errors', $response);
        $this->assertIsArray($response['errors']);
    }

    public function testValidationErrorWhenPayloadIsMalformed(): void
    {
        $this->client->request(
            'POST',
            '/api/notes',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            '{invalid_json}'
        );

        $this->assertResponseStatusCodeSame(422);
    }

    public function testValidationErrorWhenTypeIsInvalid(): void
    {
        $payload = [
            'title' => 'Invalid Type',
            'content' => 'This should fail.',
            'type' => 'invalid-type',
        ];

        $this->client->request(
            'POST',
            '/api/notes',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            is_string(json_encode($payload)) ? json_encode($payload) : null
        );

        $this->assertResponseStatusCodeSame(400);
        $content = $this->client->getResponse()->getContent();
        $response = json_decode(is_string($content) ? $content : '', true);
        $this->assertIsArray($response, 'Response is not a valid array');
        $this->assertSame([
            'error' => 'Invalid type',
        ], $response);
    }
}
