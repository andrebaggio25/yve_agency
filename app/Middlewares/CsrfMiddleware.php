<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

class CsrfMiddleware implements Middleware
{
    /** Methods that require CSRF validation */
    private const PROTECTED = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** Routes exempt from CSRF (webhooks, APIs) */
    private array $except = [
        '/api/',
        '/webhooks/',
    ];

    public function handle(Request $request, \Closure $next): Response
    {
        if (!in_array($request->method(), self::PROTECTED, true)) {
            return $next($request);
        }

        if ($this->isExempt($request->path())) {
            return $next($request);
        }

        $token   = $request->input('_csrf_token') ?? $request->input('_token') ?? $request->server('HTTP_X_CSRF_TOKEN', '');
        $session = $_SESSION['csrf_token'] ?? null;

        if (!$session || !hash_equals($session, (string) $token)) {
            if ($request->wantsJson()) {
                return Response::json(['error' => 'CSRF token mismatch'], 419);
            }
            return Response::view('errors.419', [], 419);
        }

        // Rotate token after use
        unset($_SESSION['csrf_token']);

        return $next($request);
    }

    private function isExempt(string $path): bool
    {
        foreach ($this->except as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }
        return false;
    }
}
