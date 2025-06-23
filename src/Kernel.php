<?php

namespace Bareapi;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $confDir = dirname(__DIR__) . '/config';
        $container->import($confDir . '/packages/*.yaml');
        if (is_dir($confDir . '/packages/' . $this->environment)) {
            $container->import($confDir . '/packages/' . $this->environment . '/*.yaml');
        }
        $container->import($confDir . '/{services}.yaml');
        $container->import($confDir . '/{services}_' . $this->environment . '.yaml', null, true);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $confDir = dirname(__DIR__) . '/config';
        if (is_dir($confDir . '/{routes}/' . $this->environment)) {
            $routes->import($confDir . '/{routes}/' . $this->environment . '/*.yaml');
        }
        $routes->import($confDir . '/{routes}/*.yaml');
        $routes->import($confDir . '/{routes}.yaml');
    }
}
