<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Core\View;

// Load .env.testing or .env
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// Override env for tests.
// Os testes de FEATURE (QA-03) precisam de PostgreSQL real — o schema usa JSONB,
// FILTER, SKIP LOCKED, TIMESTAMPTZ, que o SQLite não suporta. Carregamos o
// .env.testing (banco local em Docker) quando ele existir; senão ficamos no
// SQLite em memória e os testes de feature se auto-skipam.
$_ENV['APP_ENV']  = 'testing';
$_ENV['APP_KEY']  = 'test-key-32-chars-long-padding00';
$_ENV['DB_ADAPTER'] = 'sqlite';
$_ENV['DB_NAME']    = ':memory:';

$testingEnv = dirname(__DIR__) . '/.env.testing';
if (is_file($testingEnv)) {
    $vars = Dotenv::createArrayBacked(dirname(__DIR__), '.env.testing')->safeLoad();

    // Guarda-corpo: teste NUNCA pode apontar para banco remoto (produção trunca!).
    $host = $vars['DB_HOST'] ?? '';
    if (in_array($host, ['127.0.0.1', 'localhost'], true)) {
        foreach ($vars as $k => $v) {
            $_ENV[$k] = $v;
        }
        $_ENV['APP_ENV'] = 'testing';
    } else {
        fwrite(STDERR, "AVISO: .env.testing com host não-local ({$host}) — ignorado.\n");
    }
}

date_default_timezone_set('America/Sao_Paulo');

View::setBasePath(dirname(__DIR__) . '/resources/views');

// Start session for tests (suppress "headers already sent" from PHPUnit output)
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
if (!isset($_SESSION)) {
    $_SESSION = [];
}
