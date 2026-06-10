<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Support\Auth;

/**
 * Valida que o usuário tem acesso ao cliente indicado pelo parâmetro de rota.
 * O nome do parâmetro padrão é 'clientId'; pode ser customizado.
 */
class ClientAccessMiddleware implements Middleware
{
    public function __construct(private readonly string $routeParam = 'clientId') {}

    public function handle(Request $request, \Closure $next): Response
    {
        $clientId = (int) $request->param($this->routeParam);

        if ($clientId > 0 && !Auth::canAccessClient($clientId)) {
            if ($request->wantsJson()) {
                return Response::json(['error' => 'Client access denied'], 403);
            }
            return Response::view('errors.403', [], 403);
        }

        return $next($request);
    }
}
