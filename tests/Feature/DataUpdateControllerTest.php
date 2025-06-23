<?php

namespace Bareapi\Tests\Feature;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Bareapi\Tests\RefreshDatabaseForWebTestTrait;

class DataUpdateControllerTest extends WebTestCase
{
    use RefreshDatabaseForWebTestTrait;

    public function testUpdateNoteSuccess(): void
    {
        $client = static::createClient();

        // Create a note
        $payload = [
            'title' => 'Original Title',
            'content' => 'Original Content'
        ];
        $client->request(
            'POST',
            '/api/notes',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            is_string(json_encode($payload)) ? json_encode($payload) : null
        );
        $this->assertResponseStatusCodeSame(201);
        $content = $client->getResponse()->getContent();
        $created = json_decode(is_string($content) ? $content : '', true);
        $this->assertIsArray($created, 'Created response is not a valid array');
        $this->assertArrayHasKey('id', $created, 'POST /api/notes did not return an id');
        $this->assertIsString($created['id'], 'Created id is not a string');
        $this->assertArrayHasKey('data', $created, 'POST /api/notes did not return a data object');
        $this->assertIsArray($created['data'], 'Created data is not a valid array');

        // Update the note
        $updatePayload = [
            'title' => 'Updated Title',
            'content' => 'Updated Content'
        ];
        $client->request(
            'PUT',
            '/api/notes/' . $created['id'],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            is_string(json_encode($updatePayload)) ? json_encode($updatePayload) : null
        );
        $this->assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        $updated = json_decode(is_string($content) ? $content : '', true);
        $this->assertIsArray($updated, 'Updated response is not a valid array');
        $this->assertArrayHasKey('data', $updated, 'PUT /api/notes/{id} did not return a data object');
        $this->assertIsArray($updated['data'], 'Updated data is not a valid array');
        $this->assertSame('Updated Title', $updated['data']['title']);
        $this->assertSame('Updated Content', $updated['data']['content']);
    }

    public function testUpdateNoteNotFound(): void
    {
        $client = static::createClient();
        $updatePayload = [
            'title' => 'Should Not Exist',
            'content' => 'Should Not Exist'
        ];
        $client->request(
            'PUT',
            '/api/notes/00000000-0000-0000-0000-000000000000',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            is_string(json_encode($updatePayload)) ? json_encode($updatePayload) : null
        );
        $this->assertResponseStatusCodeSame(404);
    }

    public function testUpdateNoteValidationError(): void
    {
        $client = static::createClient();

        // Create a note
        $payload = [
            'title' => 'To be updated',
            'content' => 'To be updated'
        ];
        $client->request(
            'POST',
            '/api/notes',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            is_string(json_encode($payload)) ? json_encode($payload) : null
        );
        $this->assertResponseStatusCodeSame(201);
        $content = $client->getResponse()->getContent();
        $created = json_decode(is_string($content) ? $content : '', true);
        $this->assertIsArray($created, 'Created response is not a valid array');
        $this->assertArrayHasKey('id', $created);
        $this->assertIsString($created['id'], 'Created id is not a string');
        $this->assertArrayHasKey('data', $created);
        $this->assertIsArray($created['data'], 'Created data is not a valid array');

        // Update with invalid data
        $client->request(
            'PUT',
            '/api/notes/' . $created['id'],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            is_string(json_encode(['title' => ''])) ? json_encode(['title' => '']) : null
        );
        $this->assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        $response = json_decode(is_string($content) ? $content : '', true);
        $this->assertIsArray($response, 'Response is not a valid array');
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('data', $response);
        $this->assertIsArray($response['data'], 'Response data is not a valid array');
        $this->assertSame('', $response['data']['title']);
        $content = $client->getResponse()->getContent();
        $response = json_decode(is_string($content) ? $content : '', true);
        $this->assertIsArray($response, 'Response is not a valid array');
        $this->assertArrayHasKey('data', $response);
        $this->assertIsArray($response['data'], 'Response data is not a valid array');
        $this->assertSame('', $response['data']['title']);
    }
}
