<?php

namespace App\Tests\Feature;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DataCreateControllerTest extends WebTestCase
{
    public function testAuthenticatedUserCanCreateNote(): void
    {
        $client = static::createClient();
        $payload = [
            'title' => 'Meeting Notes',
            'content' => 'Discuss project milestones and deadlines.'
        ];

        $client->request(
            'POST',
            '/data/note',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $this->assertResponseStatusCodeSame(201);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $response);
        $this->assertSame('Meeting Notes', $response['title']);
        $this->assertSame('Discuss project milestones and deadlines.', $response['content']);
    }

    public function testValidationErrorWhenTitleIsMissing(): void
    {
        $client = static::createClient();
        $payload = [
            'content' => 'No title provided'
        ];

        $client->request(
            'POST',
            '/data/note',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );

        $this->assertResponseStatusCodeSame(422);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $response);
        $this->assertArrayHasKey('title', $response['errors']);
    }

    public function testValidationErrorWhenPayloadIsMalformed(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/data/note',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{invalid_json}'
        );

        $this->assertResponseStatusCodeSame(400);
    }
}
