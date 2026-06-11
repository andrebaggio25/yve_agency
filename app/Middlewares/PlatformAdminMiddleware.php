<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Support\Auth;

class PlatformAdminMiddleware implements Middleware
{
    public function handle(Request $request, \Closure $next): Response
    {
        if (!Auth::check()) {
            return Response::redirect('/login');
        }

        if (!Auth::isPlatformAdmin()) {
            if ($request->wantsJson()) {
                return Response::json(['error' => 'Forbidden'], 403);
            }
            return Response::view('errors.403', [], 403);
        }

        return $next($request);
    }
}
