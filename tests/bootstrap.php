<?php

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\ErrorHandler\Debug;

require dirname(__DIR__).'/vendor/autoload.php';

if (file_exists(dirname(__DIR__).'/config/bootstrap.php')) {
    require dirname(__DIR__).'/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

// Force test environment
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = '1';

if ($_SERVER['APP_DEBUG']) {
    umask(0000);

    if (class_exists(Debug::class)) {
        Debug::enable();
    }
}

// Ensure DAMA DoctrineTestBundle is enabled
if (!class_exists('\DAMA\DoctrineTestBundle\PHPUnit\PHPUnitExtension')) {
    echo "DAMA DoctrineTestBundle is not installed. Please run: composer require --dev dama/doctrine-test-bundle\n";
    exit(1);
}

// Note: Database creation and migrations should be run manually or via Makefile
// to avoid permission issues during test execution