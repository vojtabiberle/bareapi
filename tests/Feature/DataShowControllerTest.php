<?php

namespace App\Tests\Feature;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DataShowControllerTest extends WebTestCase
{
    public function testShowNoteReturnsNoteData(): void
    {
        $client = static::createClient();

        // First, create a note
        $payload = [
            'title' => 'Show Test',
            'content' => 'Show content'
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

        // Now, retrieve the note
        $client->request('GET', '/data/note/' . $created['id']);
        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Show Test', $response['title']);
        $this->assertSame('Show content', $response['content']);
    }

    public function testShowNoteNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/data/note/00000000-0000-0000-0000-000000000000');
        $this->assertResponseStatusCodeSame(404);
    }
}
