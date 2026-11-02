<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\UserRepository;
use App\Support\Auth;

class AuthMiddleware implements Middleware
{
    /** Revalida contra o banco no máximo a cada N segundos: é 1 ida ao Supabase. */
    private const REVALIDATE_SECONDS = 60;

    public function __construct(private readonly UserRepository $users) {}

    public function handle(Request $request, \Closure $next): Response
    {
        if (Auth::check() && $this->sessionRevoked()) {
            Auth::logout();
        }

        if (!Auth::check()) {
            if ($request->wantsJson()) {
                return Response::json(['error' => 'Unauthenticated'], 401);
            }

            flash('redirect_after_login', $request->path());
            return Response::redirect('/login');
        }

        return $next($request);
    }

    /**
     * A sessão morre quando a senha muda ou a conta é desativada. Sem isto, quem
     * roubou uma sessão seguiria dentro mesmo depois de a vítima trocar a senha.
     *
     * Quando a sessão continua válida, aproveita a ida ao banco para recarregar
     * os vínculos de cliente (conceder/revogar acesso passa a valer em até
     * `REVALIDATE_SECONDS`, não só no próximo login).
     */
    private function sessionRevoked(): bool
    {
        if (time() - (int) ($_SESSION['auth_checked_at'] ?? 0) < self::REVALIDATE_SECONDS) {
            return false;
        }

        $state = $this->users->sessionState((int) Auth::id());
        if ($state === null || $state['status'] !== 'active') {
            return true;
        }

        $current = Auth::passwordFingerprint($state['password_hash']);

        // Sessão aberta antes deste controle existir: adota a impressão digital atual.
        $stored = $_SESSION['auth_fingerprint'] ?? $current;
        if (!hash_equals((string) $stored, $current)) {
            return true;
        }

        // Os vínculos por cliente vêm na mesma ida ao banco (SEC-11): antes eles
        // eram montados só no login, então revogar acesso não tinha efeito
        // nenhum sobre a sessão já aberta — a tela dizia "Acesso removido" e o
        // usuário continuava abrindo os planos do cliente até deslogar.
        $_SESSION['client_ids'] = $this->users->loadClientIds((int) Auth::id());

        $_SESSION['auth_fingerprint'] = $current;
        $_SESSION['auth_checked_at']  = time();

        return false;
    }
}
