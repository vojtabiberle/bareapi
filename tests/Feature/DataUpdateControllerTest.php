<?php

namespace App\Tests\Feature;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DataUpdateControllerTest extends WebTestCase
{
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
            '/data/note',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );
        $this->assertResponseStatusCodeSame(201);
        $created = json_decode($client->getResponse()->getContent(), true);

        // Update the note
        $updatePayload = [
            'title' => 'Updated Title',
            'content' => 'Updated Content'
        ];
        $client->request(
            'PUT',
            '/data/note/' . $created['id'],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($updatePayload)
        );
        $this->assertResponseIsSuccessful();
        $updated = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Updated Title', $updated['title']);
        $this->assertSame('Updated Content', $updated['content']);
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
            '/data/note/00000000-0000-0000-0000-000000000000',
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
            '/data/note',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );
        $this->assertResponseStatusCodeSame(201);
        $created = json_decode($client->getResponse()->getContent(), true);

        // Update with invalid data
        $client->request(
            'PUT',
            '/data/note/' . $created['id'],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['title' => ''])
        );
        $this->assertResponseStatusCodeSame(422);
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $response);
        $this->assertArrayHasKey('title', $response['errors']);
    }
}
