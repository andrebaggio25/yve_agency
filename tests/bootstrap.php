<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Core\View;

// Load .env.testing or .env
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// Override env for tests
$_ENV['APP_ENV']  = 'testing';
$_ENV['DB_ADAPTER'] = 'sqlite';
$_ENV['DB_NAME']    = ':memory:';
$_ENV['APP_KEY']    = 'test-key-32-chars-long-padding00';

date_default_timezone_set('America/Sao_Paulo');

View::setBasePath(dirname(__DIR__) . '/resources/views');

// Start session for tests (suppress "headers already sent" from PHPUnit output)
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
if (!isset($_SESSION)) {
    $_SESSION = [];
}
