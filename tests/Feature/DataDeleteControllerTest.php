<?php

namespace Bareapi\Tests\Feature;

class DataDeleteControllerTest extends FeatureTestCase
{
    public function testDeleteNoteSuccess(): void
    {
        // Create a note
        $payload = [
            'title' => 'Delete Me',
            'content' => 'To be deleted',
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
        $created = json_decode(is_string($content) ? $content : '', true);
        $this->assertIsArray($created, 'Created response is not a valid array');
        $this->assertArrayHasKey('id', $created, 'Created response does not contain id');
        $this->assertIsString($created['id'], 'Created id is not a string');

        // Delete the note
        $this->client->request('DELETE', '/api/notes/' . $created['id']);
        $this->assertResponseStatusCodeSame(204);

        // Confirm deletion
        $this->client->request('GET', '/api/notes/' . $created['id']);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteNoteNotFound(): void
    {
        $this->client->request('DELETE', '/api/notes/00000000-0000-0000-0000-000000000000');
        $this->assertResponseStatusCodeSame(404);
    }
}
