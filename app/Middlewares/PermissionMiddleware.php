<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Support\Auth;

/**
 * Uso nas rotas:
 *   new PermissionMiddleware('clients.edit')
 * ou como string resolvida pelo Container passando o slug no construtor.
 */
class PermissionMiddleware implements Middleware
{
    public function __construct(private readonly string $permission = '') {}

    public function handle(Request $request, \Closure $next): Response
    {
        if ($this->permission && !Auth::can($this->permission)) {
            if ($request->wantsJson()) {
                return Response::json(['error' => 'Forbidden', 'required' => $this->permission], 403);
            }
            return Response::view('errors.403', ['permission' => $this->permission], 403);
        }

        return $next($request);
    }
}
