<?php

namespace Bareapi\Tests\Feature;

use Bareapi\Tests\RefreshDatabaseForWebTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DataDeleteControllerTest extends WebTestCase
{
    use RefreshDatabaseForWebTestTrait;

    public function testDeleteNoteSuccess(): void
    {
        $client = static::createClient();

        // Create a note
        $payload = [
            'title' => 'Delete Me',
            'content' => 'To be deleted',
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
        $created = json_decode(is_string($content) ? $content : '', true);
        $this->assertIsArray($created, 'Created response is not a valid array');
        $this->assertArrayHasKey('id', $created, 'Created response does not contain id');
        $this->assertIsString($created['id'], 'Created id is not a string');

        // Delete the note
        $client->request('DELETE', '/api/notes/' . $created['id']);
        $this->assertResponseStatusCodeSame(204);

        // Confirm deletion
        $client->request('GET', '/api/notes/' . $created['id']);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteNoteNotFound(): void
    {
        $client = static::createClient();
        $client->request('DELETE', '/api/notes/00000000-0000-0000-0000-000000000000');
        $this->assertResponseStatusCodeSame(404);
    }
}
