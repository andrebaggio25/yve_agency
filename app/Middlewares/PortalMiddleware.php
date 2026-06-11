<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\ClientRepository;
use App\Support\PortalAuth;

class PortalMiddleware
{
    public function __construct(private readonly ClientRepository $clientRepo) {}

    public function handle(Request $request, callable $next): Response
    {
        $token  = $request->param('portal_token');
        $client = $token ? $this->clientRepo->findByPortalToken((string) $token) : null;

        if (!$client || !$client['portal_enabled']) {
            return Response::view('portal.unavailable', [], 403);
        }

        PortalAuth::set($client);

        return $next($request);
    }
}
