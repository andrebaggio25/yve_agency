<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

/**
 * Simple file-based rate limiter (suitable for the login endpoint).
 * For high-traffic, replace with Redis/APCu backend.
 */
class RateLimitMiddleware implements Middleware
{
    public function __construct(
        private readonly int    $maxAttempts = 10,
        private readonly int    $decaySeconds = 60,
    ) {}

    public function handle(Request $request, \Closure $next): Response
    {
        $key  = 'rate_limit_' . md5($request->ip() . $request->path());
        $file = storage_path('framework/' . $key . '.json');

        $data = $this->load($file);

        if ($data['attempts'] >= $this->maxAttempts) {
            $remaining = $data['reset_at'] - time();
            if ($remaining > 0) {
                if ($request->wantsJson()) {
                    return Response::json([
                        'error'       => 'Too many requests',
                        'retry_after' => $remaining,
                    ], 429);
                }
                return Response::view('errors.429', ['retry_after' => $remaining], 429);
            }
            // Decay window passed — reset
            $data = ['attempts' => 0, 'reset_at' => time() + $this->decaySeconds];
        }

        $data['attempts']++;
        if ($data['reset_at'] === 0) {
            $data['reset_at'] = time() + $this->decaySeconds;
        }

        file_put_contents($file, json_encode($data));

        return $next($request);
    }

    private function load(string $file): array
    {
        if (!file_exists($file)) {
            return ['attempts' => 0, 'reset_at' => 0];
        }

        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : ['attempts' => 0, 'reset_at' => 0];
    }
}
