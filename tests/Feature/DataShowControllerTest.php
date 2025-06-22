<?php

namespace Bareapi\Tests\Feature;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Bareapi\Tests\RefreshDatabaseForWebTestTrait;

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
            is_string(json_encode($payload)) ? json_encode($payload) : null
        );
        $this->assertResponseStatusCodeSame(201);
        $content = $client->getResponse()->getContent();
        $created = json_decode(is_string($content) ? $content : '', true);
        $this->assertIsArray($created, 'Created response is not a valid array');
        $this->assertArrayHasKey('id', $created, 'Created response does not contain id');
        $this->assertIsString($created['id'], 'Created id is not a string');

        // Now, retrieve the note
        $client->request('GET', '/data/notes/' . $created['id']);
        $this->assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        $response = json_decode(is_string($content) ? $content : '', true);
        $this->assertIsArray($response, 'Response is not a valid array');
        $this->assertArrayHasKey('data', $response, 'Response does not contain data');
        $this->assertIsArray($response['data'], 'Data is not a valid array');
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
