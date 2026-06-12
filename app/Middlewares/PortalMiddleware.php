<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\ClientRepository;
use App\Support\PortalAuth;
use PDO;

class PortalMiddleware
{
    public function __construct(
        private readonly ClientRepository $clientRepo,
        private readonly PDO $pdo,
    ) {}

    public function handle(Request $request, callable $next): Response
    {
        $token  = $request->param('portal_token');
        $client = $token ? $this->clientRepo->findByPortalToken((string) $token) : null;

        if (!$client || !$client['portal_enabled']) {
            return Response::view('portal.unavailable', [], 403);
        }

        $stmt = $this->pdo->prepare('SELECT * FROM agencies WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $client['agency_id']]);
        $agency = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        PortalAuth::set($client, $agency ?: null);

        return $next($request);
    }
}
