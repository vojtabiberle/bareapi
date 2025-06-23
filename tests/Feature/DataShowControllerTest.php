<?php

namespace Bareapi\Tests\Feature;

class DataShowControllerTest extends FeatureTestCase
{
    public function testShowNoteReturnsNoteData(): void
    {
        // First, create a note
        $payload = [
            'title' => 'Show Test',
            'content' => 'Show content',
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

        // Now, retrieve the note
        $this->client->request('GET', '/api/notes/' . $created['id']);
        $this->assertResponseIsSuccessful();
        $content = $this->client->getResponse()->getContent();
        $response = json_decode(is_string($content) ? $content : '', true);
        $this->assertIsArray($response, 'Response is not a valid array');
        $this->assertArrayHasKey('data', $response, 'Response does not contain data');
        $this->assertIsArray($response['data'], 'Data is not a valid array');
        $this->assertSame('Show Test', $response['data']['title']);
        $this->assertSame('Show content', $response['data']['content']);
    }

    public function testShowNoteNotFound(): void
    {
        $this->client->request('GET', '/api/notes/00000000-0000-0000-0000-000000000000');
        $this->assertResponseStatusCodeSame(404);
    }
}
