<?php

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/database/migrations',
        'seeds'      => '%%PHINX_CONFIG_DIR%%/database/seeders',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment'     => $_ENV['APP_ENV'] ?? 'development',
        'development' => [
            'adapter' => $_ENV['DB_ADAPTER'] ?? 'pgsql',
            'host'    => $_ENV['DB_HOST']    ?? '127.0.0.1',
            'name'    => $_ENV['DB_NAME']    ?? 'yve_agency',
            'user'    => $_ENV['DB_USER']    ?? 'postgres',
            'pass'    => $_ENV['DB_PASS']    ?? '',
            'port'    => $_ENV['DB_PORT']    ?? '5432',
            'charset' => 'utf8',
        ],
        'testing' => [
            'adapter' => 'sqlite',
            'name'    => ':memory:',
        ],
        'production' => [
            'adapter' => $_ENV['DB_ADAPTER'] ?? 'pgsql',
            'host'    => $_ENV['DB_HOST'],
            'name'    => $_ENV['DB_NAME'],
            'user'    => $_ENV['DB_USER'],
            'pass'    => $_ENV['DB_PASS'],
            'port'    => $_ENV['DB_PORT']   ?? '5432',
            'charset' => 'utf8',
        ],
    ],
    'version_order' => 'creation',
];
