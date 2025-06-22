<?php

namespace App\Tests\Feature;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DataDeleteControllerTest extends WebTestCase
{
    public function testDeleteNoteSuccess(): void
    {
        $client = static::createClient();

        // Create a note
        $payload = [
            'title' => 'Delete Me',
            'content' => 'To be deleted'
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

        // Delete the note
        $client->request('DELETE', '/data/note/' . $created['id']);
        $this->assertResponseStatusCodeSame(204);

        // Confirm deletion
        $client->request('GET', '/data/note/' . $created['id']);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteNoteNotFound(): void
    {
        $client = static::createClient();
        $client->request('DELETE', '/data/note/00000000-0000-0000-0000-000000000000');
        $this->assertResponseStatusCodeSame(404);
    }
}
