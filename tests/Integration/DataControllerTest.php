<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;
use App\Tests\RefreshDatabaseForKernelTestTrait;

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
