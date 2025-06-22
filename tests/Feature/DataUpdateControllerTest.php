<?php

namespace App\Tests\Feature;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Tests\RefreshDatabaseForWebTestTrait;

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
            '/data/notes',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );
        $this->assertResponseStatusCodeSame(201);
        $created = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $created, 'POST /data/notes did not return an id');
        $this->assertArrayHasKey('data', $created, 'POST /data/notes did not return a data object');

        // Update the note
        $updatePayload = [
            'title' => 'Updated Title',
            'content' => 'Updated Content'
        ];
        $client->request(
            'PUT',
            '/data/notes/' . $created['id'],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($updatePayload)
        );
        $this->assertResponseIsSuccessful();
        $updated = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('data', $updated, 'PUT /data/notes/{id} did not return a data object');
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
            '/data/notes/00000000-0000-0000-0000-000000000000',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($updatePayload)
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
            '/data/notes',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );
        $this->assertResponseStatusCodeSame(201);
        $created = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $created);
        $this->assertArrayHasKey('data', $created);

        // Update with invalid data
        $client->request(
            'PUT',
            '/data/notes/' . $created['id'],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['title' => ''])
        );
        $this->assertResponseStatusCodeSame(200);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('data', $response);
        $this->assertSame('', $response['data']['title']);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('', $response['data']['title']);
    }
}
