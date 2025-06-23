<?php

declare(strict_types=1);

namespace Bareapi\Tests\Feature;

final class DataListControllerTest extends FeatureTestCase
{
    public function testFilterByValidFieldReturnsResults(): void
    {
        // Generate unique statuses for isolation
        $uniqueStatus1 = 'active_' . uniqid('', true);
        $uniqueStatus2 = 'archived_' . uniqid('', true);

        // Create two notes with different unique statuses
        $payload1 = json_encode([
            'title' => 'Note 1',
            'content' => 'Test content',
            'status' => $uniqueStatus1,
        ]);
        assert($payload1 !== false);
        $this->client->request('POST', '/api/notes', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload1);

        $payload2 = json_encode([
            'title' => 'Note 2',
            'content' => 'Test content',
            'status' => $uniqueStatus2,
        ]);
        assert($payload2 !== false);
        $this->client->request('POST', '/api/notes', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload2);

        // Filter by the first unique status (should only return Note 1)
        $this->client->request('GET', '/api/notes?status=' . urlencode($uniqueStatus1));
        $response = $this->client->getResponse();
        if ($response->getStatusCode() !== 200) {
            echo "\nResponse body: " . $response->getContent() . "\n";
        }
        $this->assertSame(200, $response->getStatusCode());
        $json = $response->getContent();
        assert(is_string($json));
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($data));
        $this->assertCount(1, $data);
        $this->assertIsArray($data[0]);
        $this->assertArrayHasKey('data', $data[0]);
        $this->assertIsArray($data[0]['data']);
        $this->assertArrayHasKey('title', $data[0]['data']);
        $this->assertArrayHasKey('status', $data[0]['data']);
        $this->assertSame('Note 1', $data[0]['data']['title']);
        $this->assertSame($uniqueStatus1, $data[0]['data']['status']);
    }

    public function testFilterByNonFilterableFieldReturns400(): void
    {
        // $client = static::createClient();

        // Create a note
        $payload3 = json_encode([
            'title' => 'Note 3',
            'content' => 'Test content',
            'status' => 'active',
        ]);
        assert($payload3 !== false);
        $this->client->request('POST', '/api/notes', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload3);

        // Attempt to filter by a non-filterable field (title)
        $this->client->request('GET', '/api/notes?title=Note 3');
        $response = $this->client->getResponse();
        $this->assertSame(400, $response->getStatusCode());
        $json = $response->getContent();
        assert(is_string($json));
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($data));
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertIsString($data['message']);
        $this->assertStringContainsString('Filtering by field "title" is not allowed', $data['message']);
    }
}
