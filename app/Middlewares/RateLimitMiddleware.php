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
        private readonly int    $maxAttempts = 5,
        private readonly int    $decaySeconds = 60,
    ) {}

    public function handle(Request $request, \Closure $next): Response
    {
        $key  = 'rate_limit_' . md5($request->ip() . $request->path());
        $file = storage_path('framework/' . $key . '.json');

        // Abre com lock exclusivo para tornar o read-modify-write atômico —
        // sem isso, requisições concorrentes subcontam as tentativas (race).
        $fp = @fopen($file, 'c+');
        if ($fp === false) {
            // Não conseguiu abrir o arquivo de estado: falha aberto para não
            // bloquear login legítimo por problema de I/O.
            return $next($request);
        }
        flock($fp, LOCK_EX);

        $raw  = stream_get_contents($fp) ?: '';
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $data = ['attempts' => 0, 'reset_at' => 0];
        }

        if (($data['attempts'] ?? 0) >= $this->maxAttempts) {
            $remaining = (int) ($data['reset_at'] ?? 0) - time();
            if ($remaining > 0) {
                flock($fp, LOCK_UN);
                fclose($fp);
                if ($request->wantsJson()) {
                    return Response::json([
                        'error'       => 'Too many requests',
                        'retry_after' => $remaining,
                    ], 429);
                }
                return Response::view('errors.429', ['retry_after' => $remaining], 429);
            }
            // Janela de decaimento passou — zera.
            $data = ['attempts' => 0, 'reset_at' => time() + $this->decaySeconds];
        }

        $data['attempts'] = (int) ($data['attempts'] ?? 0) + 1;
        if (($data['reset_at'] ?? 0) === 0) {
            $data['reset_at'] = time() + $this->decaySeconds;
        }

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        return $next($request);
    }
}
