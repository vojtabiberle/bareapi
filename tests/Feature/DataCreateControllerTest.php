<?php

namespace App\Tests\Feature;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Tests\RefreshDatabaseForWebTestTrait;

class DataCreateControllerTest extends WebTestCase
{
    use RefreshDatabaseForWebTestTrait;

    public function testAuthenticatedUserCanCreateNote(): void
    {
        $client = static::createClient();
        $payload = [
            'title' => 'Meeting Notes',
            'content' => 'Discuss project milestones and deadlines.'
        ];

        $client->request(
            'POST',
            '/data/notes',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $this->assertResponseStatusCodeSame(201);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('data', $response);
        $this->assertSame('Meeting Notes', $response['data']['title']);
        $this->assertSame('Discuss project milestones and deadlines.', $response['data']['content']);
    }

    public function testValidationErrorWhenTitleIsMissing(): void
    {
        $client = static::createClient();
        $payload = [
            'content' => 'No title provided'
        ];

        $client->request(
            'POST',
            '/data/notes',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $this->assertResponseStatusCodeSame(422);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $response);
        $this->assertIsArray($response['errors']);
    }

    public function testValidationErrorWhenPayloadIsMalformed(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/data/notes',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{invalid_json}'
        );

        $this->assertResponseStatusCodeSame(422);
    }
}
