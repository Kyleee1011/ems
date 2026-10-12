<?php

// Load Composer autoloader to make Dotenv available
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables from .env file
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

return
[
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => [
            'adapter' => 'mysql',
            'host' => $_ENV['DB_SERVER'] ?? getenv('DB_SERVER'),
            'name' => $_ENV['DB_NAME'] ?? getenv('DB_NAME'),
            'user' => $_ENV['DB_USER'] ?? getenv('DB_USER'),
            'pass' => $_ENV['DB_PASS'] ?? getenv('DB_PASS'),
            'port' => '3306',
            'charset' => 'utf8',
        ],
        'testing' => [
            'adapter' => 'mysql',
            'host' => $_ENV['DB_SERVER_TEST'] ?? getenv('DB_SERVER_TEST'),
            'name' => $_ENV['DB_NAME_TEST'] ?? getenv('DB_NAME_TEST'),
            'user' => $_ENV['DB_USER_TEST'] ?? getenv('DB_USER_TEST'),
            'pass' => $_ENV['DB_PASS_TEST'] ?? getenv('DB_PASS_TEST'),
            'port' => '3306',
            'charset' => 'utf8',
        ]
    ],
    'version_order' => 'creation'
];
