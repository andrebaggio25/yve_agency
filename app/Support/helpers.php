<?php

declare(strict_types=1);

use App\Core\Container;
use App\Core\Env;
use App\Core\Lang;
use App\Core\View;

// ─────────────────────────────────────────────────────────────────────────────
// Security
// ─────────────────────────────────────────────────────────────────────────────

/** HTML-escape; the only safe way to output user data in templates */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Generate or retrieve CSRF token for the current session */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/** Render a hidden CSRF input (use inside every <form>) */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . csrf_token() . '">';
}

/** Render a hidden _method input for PUT/DELETE form spoofing */
function method_field(string $method): string
{
    return '<input type="hidden" name="_method" value="' . strtoupper($method) . '">';
}

// ─────────────────────────────────────────────────────────────────────────────
// Environment
// ─────────────────────────────────────────────────────────────────────────────

function env(string $key, mixed $default = null): mixed
{
    return Env::get($key, $default);
}

// ─────────────────────────────────────────────────────────────────────────────
// Container
// ─────────────────────────────────────────────────────────────────────────────

function app(?string $abstract = null): mixed
{
    $container = Container::getInstance();
    return $abstract ? $container->make($abstract) : $container;
}

// ─────────────────────────────────────────────────────────────────────────────
// Paths
// ─────────────────────────────────────────────────────────────────────────────

function base_path(string $path = ''): string
{
    return dirname(__DIR__, 2) . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : '');
}

function storage_path(string $path = ''): string
{
    return base_path('storage') . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : '');
}

function resource_path(string $path = ''): string
{
    return base_path('resources') . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : '');
}

function public_path(string $path = ''): string
{
    return base_path('public') . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : '');
}

// ─────────────────────────────────────────────────────────────────────────────
// Views
// ─────────────────────────────────────────────────────────────────────────────

/** Set the layout from within a view file */
function view_layout(string $name): void
{
    View::layout($name);
}

/** Start a named section */
function view_start(string $section): void
{
    View::start($section);
}

/** End the current section */
function view_end(): void
{
    View::stop();
}

/** Output a named section inside a layout */
function view_slot(string $section, string $default = ''): string
{
    return View::slot($section, $default);
}

/** Include a partial */
function view_partial(string $name, array $data = []): string
{
    return View::partial($name, $data);
}

// ─────────────────────────────────────────────────────────────────────────────
// Flash messages (stored in session)
// ─────────────────────────────────────────────────────────────────────────────

function flash(string $key, mixed $value = null): mixed
{
    if ($value !== null) {
        $_SESSION['flash'][$key] = $value;
        return null;
    }

    $val = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $val;
}

function has_flash(string $key): bool
{
    return isset($_SESSION['flash'][$key]);
}

function flash_messages(): void
{
    if (has_flash('success')) {
        echo '<div class="mb-4 rounded-xl border border-green-500/20 bg-green-500/10 px-4 py-3 text-sm text-green-300">' . e((string) flash('success')) . '</div>';
    }
    if (has_flash('error')) {
        echo '<div class="mb-4 rounded-xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300">' . e((string) flash('error')) . '</div>';
    }
    if (has_flash('errors')) {
        $errors = flash('errors');
        if (is_array($errors)) {
            echo '<div class="mb-4 rounded-xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300"><ul class="list-disc list-inside space-y-1">';
            foreach ($errors as $msg) {
                echo '<li>' . e((string) $msg) . '</li>';
            }
            echo '</ul></div>';
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Old input (form repopulation after validation error)
// ─────────────────────────────────────────────────────────────────────────────

function old(string $key, mixed $default = ''): string
{
    $old = $_SESSION['flash']['old'] ?? [];
    return e($old[$key] ?? $default);
}

function flash_old(): array
{
    return $_SESSION['flash']['old'] ?? [];
}

// ─────────────────────────────────────────────────────────────────────────────
// Formatting
// ─────────────────────────────────────────────────────────────────────────────

function money(float $amount, string $currency = 'BRL'): string
{
    $locales = ['BRL' => 'pt_BR', 'USD' => 'en_US', 'EUR' => 'de_DE'];
    $locale  = $locales[$currency] ?? 'pt_BR';

    $fmt = new NumberFormatter($locale, NumberFormatter::CURRENCY);
    return $fmt->formatCurrency($amount, $currency) ?: number_format($amount, 2, ',', '.');
}

function date_fmt(string|\DateTimeInterface|null $date, string $format = 'd/m/Y'): string
{
    if ($date === null) return '';
    $dt = is_string($date) ? new \DateTime($date) : $date;
    return $dt->format($format);
}

// ─────────────────────────────────────────────────────────────────────────────
// Logging shortcut
// ─────────────────────────────────────────────────────────────────────────────

// ─────────────────────────────────────────────────────────────────────────────
// Internationalisation
// ─────────────────────────────────────────────────────────────────────────────

/** Translate a key. Usage: t('approval.approve_all'), t('common.save') */
function t(string $key, array $replace = []): string
{
    return Lang::t($key, $replace);
}

/** Get/set the current locale */
function locale(?string $set = null): string
{
    if ($set !== null) {
        Lang::setLocale($set);
    }
    return Lang::getLocale();
}

// ─────────────────────────────────────────────────────────────────────────────
// Logging shortcut
// ─────────────────────────────────────────────────────────────────────────────

function logger(string $message, array $context = [], string $channel = 'app'): void
{
    \App\Core\Logger::channel($channel)->info($message, $context);
}
