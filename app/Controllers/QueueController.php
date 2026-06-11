<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\NotificationService;
use App\Services\AdsSyncService;
use App\Services\OrganicSyncService;

/**
 * Endpoint chamado pelo cron externo via GET.
 * Protegido por token secreto no .env (QUEUE_SECRET).
 */
class QueueController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly AdsSyncService      $adsSync,
        private readonly OrganicSyncService  $organicSync,
    ) {}

    public function run(Request $request): Response
    {
        $secret = env('QUEUE_SECRET', '');
        $token  = $request->query('token', '');

        if (empty($secret) || !hash_equals($secret, $token)) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $limit     = min((int) $request->query('limit', '10'), 50);
        $processed = $this->notifications->processQueue($limit);

        return Response::json([
            'success'   => true,
            'processed' => $processed,
            'timestamp' => date('c'),
        ]);
    }

    public function syncAds(Request $request): Response
    {
        $secret = env('QUEUE_SECRET', '');
        $token  = $request->query('token', '');

        if (empty($secret) || !hash_equals($secret, $token)) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        try {
            $results = $this->adsSync->syncAll();
            return Response::json(['success' => true, 'results' => $results, 'timestamp' => date('c')]);
        } catch (\Throwable $e) {
            return Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function syncOrganic(Request $request): Response
    {
        $secret = env('QUEUE_SECRET', '');
        $token  = $request->query('token', '');

        if (empty($secret) || !hash_equals($secret, $token)) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        try {
            $results = $this->organicSync->syncAll();
            return Response::json(['success' => true, 'results' => $results, 'timestamp' => date('c')]);
        } catch (\Throwable $e) {
            return Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
