<?php

namespace App\Tests;

trait RefreshDatabaseForKernelTestTrait
{
    protected function setUp(): void
    {
        parent::setUp();

        $kernel = self::bootKernel();
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application($kernel);
        $application->setAutoExit(false);
        $input = new \Symfony\Component\Console\Input\ArrayInput([
            'command' => 'doctrine:migrations:migrate',
            '--env' => 'test',
            '--no-interaction' => true,
        ]);
        $output = new \Symfony\Component\Console\Output\NullOutput();
        $application->run($input, $output);
    }
}
