<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AutomationRepository;
use App\Services\AutomationService;
use App\Services\NotificationService;
use App\Services\AdsSyncService;
use App\Services\OrganicSyncService;
use App\Services\DriveSyncService;

/**
 * Endpoints chamados pelo cron externo via GET.
 * Protegidos por token secreto no .env (QUEUE_SECRET).
 */
class QueueController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly AdsSyncService      $adsSync,
        private readonly OrganicSyncService  $organicSync,
        private readonly AutomationRepository $automationRepo,
        private readonly AutomationService    $automations,
        private readonly DriveSyncService     $driveSync,
    ) {}

    public function run(Request $request): Response
    {
        if ($resp = $this->guard($request)) return $resp;

        $limit     = min((int) $request->query('limit', '10'), 50);
        $processed = $this->notifications->processQueue($limit);

        return Response::json(['success' => true, 'processed' => $processed, 'timestamp' => date('c')]);
    }

    public function syncAds(Request $request): Response
    {
        if ($resp = $this->guard($request)) return $resp;

        try {
            $results = $this->adsSync->syncAll();
            return Response::json(['success' => true, 'results' => $results, 'timestamp' => date('c')]);
        } catch (\Throwable $e) {
            return Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function syncOrganic(Request $request): Response
    {
        if ($resp = $this->guard($request)) return $resp;

        try {
            $results = $this->organicSync->syncAll();
            return Response::json(['success' => true, 'results' => $results, 'timestamp' => date('c')]);
        } catch (\Throwable $e) {
            return Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** Reconcilia as galerias de Drive de todos os clientes com o Google Drive. */
    public function syncDrive(Request $request): Response
    {
        if ($resp = $this->guard($request)) return $resp;

        try {
            $results = $this->driveSync->syncAll();
            return Response::json(['success' => true, 'results' => $results, 'timestamp' => date('c')]);
        } catch (\Throwable $e) {
            return Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ── Motor de automação ─────────────────────────────────────────────────────

    /**
     * Enfileira jobs para todas as regras agendadas que estão na hora de rodar.
     * Equivale ao bin/scheduler.php (mas dirigido por cron HTTP).
     */
    public function scheduler(Request $request): Response
    {
        if ($resp = $this->guard($request)) return $resp;

        $pdo   = Database::connection();
        $rules = $this->automationRepo->dueScheduledRules();
        $queued = 0;

        foreach ($rules as $rule) {
            $payload = json_encode([
                'job'  => \App\Jobs\RunAutomationRuleJob::class,
                'data' => ['rule_id' => (int) $rule['id']],
            ]);

            $pdo->prepare("
                INSERT INTO jobs (agency_id, queue, payload, available_at, status, created_at, updated_at)
                VALUES (:a, 'automations', :payload, NOW(), 'pending', NOW(), NOW())
            ")->execute([':a' => $rule['agency_id'], ':payload' => $payload]);

            $next = $this->automations->computeNext(
                $rule['frequency'] ?? null,
                $rule['scheduled_day'] ?? null,
                $rule['scheduled_time'] ?? null,
            );
            $this->automationRepo->touchRun((int) $rule['id'], $next);
            $queued++;
        }

        return Response::json(['success' => true, 'queued' => $queued, 'timestamp' => date('c')]);
    }

    /**
     * Processa um lote da fila genérica `jobs`. Equivale ao bin/worker.php, mas
     * sem loop infinito (um lote por requisição HTTP).
     */
    public function work(Request $request): Response
    {
        if ($resp = $this->guard($request)) return $resp;

        $pdo   = Database::connection();
        $limit = min((int) $request->query('limit', '25'), 50);
        $done = 0; $failed = 0;

        for ($i = 0; $i < $limit; $i++) {
            $job = $this->reserveJob($pdo);
            if (!$job) break;

            if ($this->processJob($pdo, $job)) $done++; else $failed++;
        }

        return Response::json(['success' => true, 'done' => $done, 'failed' => $failed, 'timestamp' => date('c')]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function guard(Request $request): ?Response
    {
        $secret = env('QUEUE_SECRET', '');
        $token  = $request->query('token', '');

        if (empty($secret) || !hash_equals($secret, (string) $token)) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }
        return null;
    }

    private function reserveJob(\PDO $pdo): ?array
    {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            SELECT * FROM jobs
            WHERE status = 'pending' AND available_at <= NOW() AND attempts < max_attempts
            ORDER BY available_at ASC
            LIMIT 1
            FOR UPDATE SKIP LOCKED
        ");
        $stmt->execute();
        $job = $stmt->fetch();

        if (!$job) { $pdo->rollBack(); return null; }

        $pdo->prepare("UPDATE jobs SET status = 'reserved', reserved_at = NOW(), attempts = attempts + 1 WHERE id = :id")
            ->execute([':id' => $job['id']]);
        $pdo->commit();

        $job['attempts'] = (int) $job['attempts'] + 1;
        return $job;
    }

    private function processJob(\PDO $pdo, array $job): bool
    {
        $payload = json_decode($job['payload'], true) ?? [];
        $class   = $payload['job'] ?? null;

        if (!$class || !class_exists($class)) {
            $pdo->prepare("UPDATE jobs SET status = 'failed', last_error = :e, updated_at = NOW() WHERE id = :id")
                ->execute([':e' => "Job class [{$class}] not found.", ':id' => $job['id']]);
            return false;
        }

        try {
            (new $class())->handle($payload['data'] ?? []);
            $pdo->prepare("UPDATE jobs SET status = 'done', updated_at = NOW() WHERE id = :id")
                ->execute([':id' => $job['id']]);
            return true;
        } catch (\Throwable $e) {
            $attempts = (int) $job['attempts'];
            $maxAttempts = (int) ($job['max_attempts'] ?? 3);

            if ($attempts >= $maxAttempts) {
                $pdo->prepare("UPDATE jobs SET status = 'failed', last_error = :e, updated_at = NOW() WHERE id = :id")
                    ->execute([':e' => $e->getMessage(), ':id' => $job['id']]);
            } else {
                $backoff = (int) (pow(2, $attempts) * 10);
                $available = date('Y-m-d H:i:s', time() + $backoff);
                $pdo->prepare("UPDATE jobs SET status = 'pending', available_at = :av, last_error = :e, updated_at = NOW() WHERE id = :id")
                    ->execute([':av' => $available, ':e' => $e->getMessage(), ':id' => $job['id']]);
            }
            return false;
        }
    }
}
