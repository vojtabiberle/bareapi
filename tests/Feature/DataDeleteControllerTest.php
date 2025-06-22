<?php

namespace Bareapi\Tests\Feature;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Bareapi\Tests\RefreshDatabaseForWebTestTrait;

class DataDeleteControllerTest extends WebTestCase
{
    use RefreshDatabaseForWebTestTrait;

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
            '/data/notes',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );
        $this->assertResponseStatusCodeSame(201);
        $created = json_decode($client->getResponse()->getContent(), true);

        // Delete the note
        $client->request('DELETE', '/data/notes/' . $created['id']);
        $this->assertResponseStatusCodeSame(204);

        // Confirm deletion
        $client->request('GET', '/data/notes/' . $created['id']);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteNoteNotFound(): void
    {
        $client = static::createClient();
        $client->request('DELETE', '/data/notes/00000000-0000-0000-0000-000000000000');
        $this->assertResponseStatusCodeSame(404);
    }
}
