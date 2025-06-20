<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

# Load test env first, then fallback to .env
if (file_exists(dirname(__DIR__).'/.env.test')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env.test');
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}
