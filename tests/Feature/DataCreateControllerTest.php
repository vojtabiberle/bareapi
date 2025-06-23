<?php

namespace Bareapi\Tests\Feature;

use Bareapi\Tests\RefreshDatabaseForWebTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DataCreateControllerTest extends WebTestCase
{
    use RefreshDatabaseForWebTestTrait;

    public function testAuthenticatedUserCanCreateNote(): void
    {
        $client = static::createClient();
        $payload = [
            'title' => 'Meeting Notes',
            'content' => 'Discuss project milestones and deadlines.',
        ];

        $client->request(
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
        $content = $client->getResponse()->getContent();
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
        $client = static::createClient();
        $payload = [
            'content' => 'No title provided',
        ];

        $client->request(
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
        $content = $client->getResponse()->getContent();
        $response = json_decode(is_string($content) ? $content : '', true);
        $this->assertIsArray($response, 'Response is not a valid array');
        $this->assertArrayHasKey('errors', $response);
        $this->assertIsArray($response['errors']);
    }

    public function testValidationErrorWhenPayloadIsMalformed(): void
    {
        $client = static::createClient();

        $client->request(
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
        $client = static::createClient();
        $payload = [
            'title' => 'Invalid Type',
            'content' => 'This should fail.',
            'type' => 'invalid-type',
        ];

        $client->request(
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
        $content = $client->getResponse()->getContent();
        $response = json_decode(is_string($content) ? $content : '', true);
        $this->assertIsArray($response, 'Response is not a valid array');
        $this->assertSame([
            'error' => 'Invalid type',
        ], $response);
    }
}
