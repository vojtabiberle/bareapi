<?php

namespace Bareapi\Tests;

trait RefreshDatabaseForWebTestTrait
{
    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $server
     */
    public static function createClient(array $options = [], array $server = []): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = parent::createClient($options, $server);

        $kernel = $client->getKernel();
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application($kernel);
        $application->setAutoExit(false);
        $input = new \Symfony\Component\Console\Input\ArrayInput([
            'command' => 'doctrine:migrations:migrate',
            '--env' => 'test',
            '--no-interaction' => true,
        ]);
        $output = new \Symfony\Component\Console\Output\NullOutput();
        $application->run($input, $output);

        return $client;
    }
}
