<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Support\Auth;

class AuthMiddleware implements Middleware
{
    public function handle(Request $request, \Closure $next): Response
    {
        if (!Auth::check()) {
            if ($request->wantsJson()) {
                return Response::json(['error' => 'Unauthenticated'], 401);
            }

            flash('redirect_after_login', $request->path());
            return Response::redirect('/login');
        }

        return $next($request);
    }
}
