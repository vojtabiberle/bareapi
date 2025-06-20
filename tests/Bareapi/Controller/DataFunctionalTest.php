<?php

namespace Bareapi\Tests\Controller;

use Bareapi\Entity\MetaObject;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DataFunctionalTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $em = self::getContainer()->get('doctrine')->getManager();
        $tool = new SchemaTool($em);
        $classes = [
            $em->getClassMetadata(MetaObject::class),
        ];
        $tool->dropSchema($classes);
        $tool->createSchema($classes);
    }

    public function testCreateShowUpdateDeleteFlow(): void
    {
        $client = static::createClient();

        // Create
        $payload = ['title' => 'Test note', 'content' => 'Hello world'];
        $client->request(
            'POST',
            '/data/notes',
            [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload)
        );
        $this->assertResponseStatusCodeSame(201);
        $created = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $created);
        $id = $created['id'];

        // Show
        $client->request('GET', "/data/notes/{$id}");
        $this->assertResponseIsSuccessful();
        $shown = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Test note', $shown['data']['title']);

        // Update
        $client->request(
            'PUT',
            "/data/notes/{$id}",
            [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['title' => 'Updated'])
        );
        $this->assertResponseIsSuccessful();
        $updated = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Updated', $updated['data']['title']);

        // Delete
        $client->request('DELETE', "/data/notes/{$id}");
        $this->assertResponseStatusCodeSame(204);

        // Confirm delete
        $client->request('GET', "/data/notes/{$id}");
        $this->assertResponseStatusCodeSame(404);
    }
}