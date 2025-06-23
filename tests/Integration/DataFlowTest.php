<?php

declare(strict_types=1);

namespace Bareapi\Tests\Integration;

final class DataFlowTest extends \Bareapi\Tests\Feature\FeatureTestCase
{
    public function testCrudFlow(): void
    {
        // Create
        $payload = [
            'title' => 'Integration Note',
            'content' => 'Integration Content',
        ];
        $jsonPayload = json_encode($payload);
        $this->assertIsString($jsonPayload, 'json_encode failed');
        $this->client->request('POST', '/api/notes', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $jsonPayload);
        $this->assertResponseStatusCodeSame(201);
        $content = $this->client->getResponse()->getContent();
        $this->assertIsString($content, 'Response content is not a string');
        $data = json_decode($content, true);
        $this->assertIsArray($data, 'json_decode did not return array');
        $this->assertArrayHasKey('id', $data);
        $this->assertIsString($data['id'], 'id is not a string');
        $id = $data['id'];

        // Fetch
        $this->client->request('GET', '/api/notes/' . $id);
        $this->assertResponseIsSuccessful();
        $content = $this->client->getResponse()->getContent();
        $this->assertIsString($content, 'Response content is not a string');
        $fetched = json_decode($content, true);
        $this->assertIsArray($fetched, 'json_decode did not return array');
        $this->assertArrayHasKey('data', $fetched);
        $this->assertIsArray($fetched['data'], 'data is not an array');
        $this->assertArrayHasKey('title', $fetched['data']);
        $this->assertSame($payload['title'], $fetched['data']['title']);

        // Update
        $updatedPayload = [
            'title' => 'Updated Title',
            'content' => 'Integration Content',
        ];
        $jsonUpdated = json_encode($updatedPayload);
        $this->assertIsString($jsonUpdated, 'json_encode failed');
        $this->client->request('PUT', '/api/notes/' . $id, [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $jsonUpdated);
        $this->assertResponseIsSuccessful();
        $content = $this->client->getResponse()->getContent();
        $this->assertIsString($content, 'Response content is not a string');
        $updated = json_decode($content, true);
        $this->assertIsArray($updated, 'json_decode did not return array');
        $this->assertArrayHasKey('data', $updated);
        $this->assertIsArray($updated['data'], 'data is not an array');
        $this->assertArrayHasKey('title', $updated['data']);
        $this->assertSame('Updated Title', $updated['data']['title']);

        // Delete
        $this->client->request('DELETE', '/api/notes/' . $id);
        $this->assertResponseStatusCodeSame(204);

        // Confirm deletion
        $this->client->request('GET', '/api/notes/' . $id);
        $this->assertResponseStatusCodeSame(404);
    }
}
