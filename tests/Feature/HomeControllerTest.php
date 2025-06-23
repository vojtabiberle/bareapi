<?php

declare(strict_types=1);

namespace Bareapi\Tests\Feature;

class HomeControllerTest extends FeatureTestCase
{
    public function testHomePageIsSuccessful(): void
    {
        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'BareAPI');
    }
}
