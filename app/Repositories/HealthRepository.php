<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

/**
 * Leituras de saúde do sistema (OBS-01).
 *
 * Sem escopo de agência: é visão de plataforma. Consultas propositalmente
 * baratas — o `/health` pode ser chamado de minuto em minuto por um monitor.
 */
class HealthRepository extends Repository
{
    protected string $table = 'jobs';

    /** Estado da fila genérica: quantos esperando, quantos falharam de vez. */
    public function queueStats(): array
    {
        $row = $this->first(
            "SELECT
                COUNT(*) FILTER (WHERE status = 'pending')  AS pending,
                COUNT(*) FILTER (WHERE status = 'reserved') AS reserved,
                COUNT(*) FILTER (WHERE status = 'failed')   AS failed,
                MIN(available_at) FILTER (WHERE status = 'pending') AS oldest_pending_at
             FROM jobs"
        ) ?? [];

        return [
            'pending'           => (int) ($row['pending']  ?? 0),
            'reserved'          => (int) ($row['reserved'] ?? 0),
            'failed'            => (int) ($row['failed']   ?? 0),
            'oldest_pending_at' => $row['oldest_pending_at'] ?? null,
        ];
    }

    /** Fila de notificações (a segunda fila — ver INFRA-01). */
    public function notificationQueueStats(): array
    {
        $row = $this->first(
            "SELECT
                COUNT(*) FILTER (WHERE status = 'pending') AS pending,
                COUNT(*) FILTER (WHERE status = 'failed')  AS failed
             FROM notification_jobs"
        ) ?? [];

        return [
            'pending' => (int) ($row['pending'] ?? 0),
            'failed'  => (int) ($row['failed']  ?? 0),
        ];
    }

    /**
     * Jobs que falharam definitivamente (estouraram max_attempts) desde $since.
     * É o que dispara o alerta: job morto é trabalho que ninguém fez.
     */
    public function recentlyFailedJobs(string $since, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, agency_id, queue, last_error, attempts, updated_at
             FROM jobs
             WHERE status = 'failed' AND updated_at >= :since
             ORDER BY updated_at DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':since', $since);
        $stmt->bindValue(':lim', max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Contas de anúncio/orgânico paradas há mais de $hours horas.
     * Sync quebrado é silencioso: o cliente só descobre no relatório errado.
     */
    public function staleSyncAccounts(int $hours = 48): array
    {
        return $this->all(
            "SELECT 'ads' AS kind, a.id, a.name, a.agency_id, a.last_synced_at
             FROM ad_accounts a
             WHERE a.status = 'active'
               AND (a.last_synced_at IS NULL OR a.last_synced_at < NOW() - (:h1 || ' hours')::interval)
             UNION ALL
             SELECT 'organic' AS kind, o.id, o.username AS name, o.agency_id, o.last_synced_at
             FROM organic_accounts o
             WHERE o.status = 'active'
               AND (o.last_synced_at IS NULL OR o.last_synced_at < NOW() - (:h2 || ' hours')::interval)
             ORDER BY last_synced_at NULLS FIRST",
            [':h1' => (string) $hours, ':h2' => (string) $hours]
        );
    }
}
