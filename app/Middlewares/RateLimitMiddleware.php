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
        protected readonly int    $maxAttempts = 5,
        protected readonly int    $decaySeconds = 60,
    ) {}

    public function handle(Request $request, \Closure $next): Response
    {
        $bucket = $this->bucket($request);
        if ($bucket === null) {
            // Requisição que não entra na contagem (ver PortalRateLimitMiddleware).
            return $next($request);
        }

        $file = storage_path('framework/' . $bucket['key'] . '.json');

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

        if (($data['attempts'] ?? 0) >= $bucket['max']) {
            $remaining = (int) ($data['reset_at'] ?? 0) - time();
            if ($remaining > 0) {
                flock($fp, LOCK_UN);
                fclose($fp);
                return $this->tooManyRequests($request, $remaining);
            }
            // Janela de decaimento passou — zera.
            $data = ['attempts' => 0, 'reset_at' => time() + $bucket['decay']];
        }

        $data['attempts'] = (int) ($data['attempts'] ?? 0) + 1;
        if (($data['reset_at'] ?? 0) === 0) {
            $data['reset_at'] = time() + $bucket['decay'];
        }

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        return $next($request);
    }

    /**
     * Cesta desta requisição: arquivo de estado, teto e janela em segundos.
     * `null` = não contar (a subclasse do portal usa isso para deixar a leitura
     * passar). Chave por IP + path: o login tem um contador por rota.
     *
     * @return array{key:string,max:int,decay:int}|null
     */
    protected function bucket(Request $request): ?array
    {
        return [
            'key'   => 'rate_limit_' . md5($request->ip() . $request->path()),
            'max'   => $this->maxAttempts,
            'decay' => $this->decaySeconds,
        ];
    }

    /** Resposta de estouro (a subclasse do portal traduz e usa a tela dele). */
    protected function tooManyRequests(Request $request, int $retryAfter): Response
    {
        if ($request->wantsJson()) {
            return Response::json([
                'error'       => 'Too many requests',
                'retry_after' => $retryAfter,
            ], 429);
        }

        return Response::view('errors.429', ['retry_after' => $retryAfter], 429);
    }
}
