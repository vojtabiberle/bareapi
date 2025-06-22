<?php

namespace App\Tests\Feature;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Tests\RefreshDatabaseForWebTestTrait;

class DataShowControllerTest extends WebTestCase
{
    use RefreshDatabaseForWebTestTrait;

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
            '/data/notes',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );
        $this->assertResponseStatusCodeSame(201);
        $created = json_decode($client->getResponse()->getContent(), true);

        // Now, retrieve the note
        $client->request('GET', '/data/notes/' . $created['id']);
        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Show Test', $response['data']['title']);
        $this->assertSame('Show content', $response['data']['content']);
    }

    public function testShowNoteNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/data/notes/00000000-0000-0000-0000-000000000000');
        $this->assertResponseStatusCodeSame(404);
    }
}
