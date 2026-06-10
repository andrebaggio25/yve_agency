<?php

declare(strict_types=1);

// ─────────────────────────────────────────────────────────────────────────────
// Autoload
// ─────────────────────────────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/vendor/autoload.php';

// ─────────────────────────────────────────────────────────────────────────────
// Environment
// ─────────────────────────────────────────────────────────────────────────────
use Dotenv\Dotenv;
use App\Core\{Container, Database, Router, View};

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// ─────────────────────────────────────────────────────────────────────────────
// Error reporting
// ─────────────────────────────────────────────────────────────────────────────
if (env('APP_ENV', 'production') === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// ─────────────────────────────────────────────────────────────────────────────
// Timezone
// ─────────────────────────────────────────────────────────────────────────────
date_default_timezone_set('America/Sao_Paulo');

// ─────────────────────────────────────────────────────────────────────────────
// Secure session
// ─────────────────────────────────────────────────────────────────────────────
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['SERVER_PORT'] ?? 80) == 443;

session_name(env('SESSION_NAME', 'yve_session'));
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', $isHttps ? '1' : '0');
ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime', (string) env('SESSION_LIFETIME', 7200));
session_start();

// ─────────────────────────────────────────────────────────────────────────────
// Container
// ─────────────────────────────────────────────────────────────────────────────
$container = Container::getInstance();

// Core singletons
$container->singleton(Router::class, fn($c) => new Router($c));

// ─────────────────────────────────────────────────────────────────────────────
// View
// ─────────────────────────────────────────────────────────────────────────────
View::setBasePath(resource_path('views'));

// ─────────────────────────────────────────────────────────────────────────────
// Routes
// ─────────────────────────────────────────────────────────────────────────────
$router = $container->make(Router::class);

require_once dirname(__DIR__) . '/routes/web.php';
require_once dirname(__DIR__) . '/routes/api.php';

// ─────────────────────────────────────────────────────────────────────────────
// Dispatch
// ─────────────────────────────────────────────────────────────────────────────
$request = \App\Core\Request::fromGlobals();
$container->instance(\App\Core\Request::class, $request);

$router->dispatch($request);
