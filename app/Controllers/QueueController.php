<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AutomationRepository;
use App\Repositories\JobRepository;
use App\Services\AutomationService;
use App\Services\HealthService;
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
        private readonly JobRepository        $jobs,
        private readonly HealthService        $health,
    ) {}

    /**
     * Compatibilidade: era o cron da fila de notificações (que tinha mecanismo
     * próprio). Com a fila unificada (INFRA-01), notificação é um job comum —
     * então este endpoint agora **processa a fila única**, igual ao /queue/work.
     * Mantido para não quebrar o cron já configurado nos hostings.
     *
     * Também resgata entregas pendentes sem job (legado da fila antiga).
     */
    public function run(Request $request): Response
    {
        if ($resp = $this->guard($request)) return $resp;

        $rescued   = $this->notifications->rescueOrphanDeliveries();
        $processed = $this->drainQueue(min((int) $request->query('limit', '25'), 50));

        return Response::json([
            'success'   => true,
            'rescued'   => $rescued,
            'done'      => $processed['done'],
            'failed'    => $processed['failed'],
            'timestamp' => date('c'),
        ]);
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

        $rules  = $this->automationRepo->dueScheduledRules();
        $queued = 0;

        foreach ($rules as $rule) {
            $this->jobs->enqueue((int) $rule['agency_id'], 'automations', [
                'job'  => \App\Jobs\RunAutomationRuleJob::class,
                'data' => ['rule_id' => (int) $rule['id']],
            ]);

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

        $r = $this->drainQueue(min((int) $request->query('limit', '25'), 50));

        return Response::json([
            'success'   => true,
            'done'      => $r['done'],
            'failed'    => $r['failed'],
            'timestamp' => date('c'),
        ]);
    }

    /**
     * Processa um lote da fila e mantém o heartbeat/alerta (OBS-01).
     * @return array{done:int,failed:int}
     */
    private function drainQueue(int $limit): array
    {
        $done = 0; $failed = 0;

        for ($i = 0; $i < $limit; $i++) {
            $job = $this->jobs->reserveNext();
            if (!$job) break;

            if ($this->processJob($job)) $done++; else $failed++;
        }

        // Heartbeat (o /health sabe se o cron parou) + alerta quando job morre
        // ou sync congela. Nunca deixe a falha de um alerta derrubar a fila.
        try {
            $this->health->recordCronRun('work');
            $this->health->checkAndAlert();
        } catch (\Throwable $e) {
            error_log('[queue] health/alerta falhou: ' . $e->getMessage());
        }

        return ['done' => $done, 'failed' => $failed];
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

    private function processJob(array $job): bool
    {
        $payload = json_decode((string) $job['payload'], true) ?? [];
        $class   = $payload['job'] ?? null;

        if (!$class || !class_exists($class)) {
            $this->jobs->markFailed($job['id'], "Job class [{$class}] not found.");
            return false;
        }

        try {
            (new $class())->handle($payload['data'] ?? []);
            $this->jobs->markDone($job['id']);
            return true;
        } catch (\Throwable $e) {
            $attempts    = (int) $job['attempts'];
            $maxAttempts = (int) ($job['max_attempts'] ?? 3);

            if ($attempts >= $maxAttempts) {
                $this->jobs->markFailed($job['id'], $e->getMessage());
            } else {
                // Backoff exponencial: 20s, 40s, 80s…
                $this->jobs->retryLater($job['id'], (int) (pow(2, $attempts) * 10), $e->getMessage());
            }
            return false;
        }
    }
}
