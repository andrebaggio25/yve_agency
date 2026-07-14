<?php

/**
 * Config do Phinx para o BANCO DE TESTE (QA-03).
 *
 * Existe separada de propósito: o `phinx.php` normal carrega o `.env` — que
 * aponta para o **Supabase de produção**. Rodar `phinx migrate` com ele e umas
 * variáveis de ambiente por cima é uma armadilha: o dotenv já populou `$_ENV`,
 * e a migration iria para produção sem avisar.
 *
 * Aqui lemos **apenas** o `.env.testing` e travamos o host: se não for local,
 * abortamos. Um teste jamais deve poder tocar em dado real.
 */

require_once __DIR__ . '/vendor/autoload.php';

$env = [];
foreach (file(__DIR__ . '/.env.testing', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
        continue;
    }
    [$k, $v] = explode('=', $line, 2);
    $env[trim($k)] = trim($v);
}

$host = $env['DB_HOST'] ?? '';
if (!in_array($host, ['127.0.0.1', 'localhost', 'db', 'postgres'], true)) {
    fwrite(STDERR, "ABORTADO: .env.testing aponta para host não-local ({$host}).\n");
    exit(1);
}

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/database/migrations',
        'seeds'      => '%%PHINX_CONFIG_DIR%%/database/seeders',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment'     => 'testing',
        'testing' => [
            'adapter' => 'pgsql',
            'host'    => $host,
            'name'    => $env['DB_NAME'] ?? 'yve_test',
            'user'    => $env['DB_USER'] ?? 'postgres',
            'pass'    => $env['DB_PASS'] ?? '',
            'port'    => $env['DB_PORT'] ?? '55432',
            'charset' => 'utf8',
        ],
    ],
    'version_order' => 'creation',
];
