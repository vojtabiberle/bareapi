<?php

declare(strict_types=1);

// Debug: Output current APP_ENV to verify test environment
$appEnv = $_SERVER['APP_ENV'] ?? getenv('APP_ENV');
fwrite(STDERR, 'APP_ENV=' . (is_string($appEnv) ? $appEnv : '') . PHP_EOL);

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;

require dirname(__DIR__) . '/vendor/autoload.php';

(new Filesystem())->remove(dirname(__DIR__) . '/var/cache/test');

// Load test env first, then fallback to .env
if (file_exists(dirname(__DIR__) . '/.env.test')) {
    (new Dotenv())->bootEnv(dirname(__DIR__) . '/.env.test');
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
}
