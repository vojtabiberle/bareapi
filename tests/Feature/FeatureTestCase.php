<?php

declare(strict_types=1);

namespace Bareapi\Tests\Feature;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class FeatureTestCase extends WebTestCase
{
    use \Bareapi\Tests\RefreshDatabaseForWebTestTrait;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    protected $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        // Each test should create its own client to avoid double kernel boot.
    }
}
