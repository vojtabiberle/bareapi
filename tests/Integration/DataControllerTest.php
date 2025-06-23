<?php

declare(strict_types=1);

namespace Bareapi\Tests\Integration;

use Bareapi\Tests\RefreshDatabaseForKernelTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

class DataControllerTest extends KernelTestCase
{
    use RefreshDatabaseForKernelTestTrait;

    public function testRoutesAreRegistered(): void
    {
        self::bootKernel();
        /** @var RouterInterface $router */
        $router = self::getContainer()->get(RouterInterface::class);

        $routes = $router->getRouteCollection();
        $this->assertNotNull($routes->get('data_list'));
        $this->assertNotNull($routes->get('data_create'));
        $this->assertNotNull($routes->get('data_show'));
        $this->assertNotNull($routes->get('data_update'));
        $this->assertNotNull($routes->get('data_delete'));
    }
}
