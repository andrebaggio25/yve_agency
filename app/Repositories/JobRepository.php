<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;
use PDO;

/**
 * Fila genérica de jobs (tabela `jobs`).
 *
 * Sem `agencyScope()`: a fila é processada por worker/cron fora de sessão —
 * cada job carrega o `agency_id` no próprio registro. Centraliza aqui o SQL
 * que estava espalhado por QueueController/TaskController (ARCH-01).
 */
class JobRepository extends Repository
{
    protected string $table = 'jobs';

    /** Enfileira um job. $payload = ['job' => Classe::class, 'data' => [...]]. */
    public function enqueue(int $agencyId, string $queue, array $payload): void
    {
        $this->query(
            "INSERT INTO jobs (agency_id, queue, payload, available_at, status, created_at, updated_at)
             VALUES (:a, :q, :p, NOW(), 'pending', NOW(), NOW())",
            [
                ':a' => $agencyId,
                ':q' => $queue,
                ':p' => (string) json_encode($payload),
            ]
        );
    }

    /**
     * Reserva o próximo job pendente de forma concorrente-segura.
     * `FOR UPDATE SKIP LOCKED`: dois workers nunca pegam o mesmo job.
     */
    public function reserveNext(): ?array
    {
        $this->pdo->beginTransaction();

        $stmt = $this->pdo->prepare(
            "SELECT * FROM jobs
             WHERE status = 'pending' AND available_at <= NOW() AND attempts < max_attempts
             ORDER BY available_at ASC
             LIMIT 1
             FOR UPDATE SKIP LOCKED"
        );
        $stmt->execute();
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$job) {
            $this->pdo->rollBack();
            return null;
        }

        $this->pdo->prepare(
            "UPDATE jobs SET status = 'reserved', reserved_at = NOW(), attempts = attempts + 1 WHERE id = :id"
        )->execute([':id' => $job['id']]);

        $this->pdo->commit();

        $job['attempts'] = (int) $job['attempts'] + 1;
        return $job;
    }

    public function markDone(int|string $id): void
    {
        $this->query("UPDATE jobs SET status = 'done', updated_at = NOW() WHERE id = :id", [':id' => $id]);
    }

    public function markFailed(int|string $id, string $error): void
    {
        $this->query(
            "UPDATE jobs SET status = 'failed', last_error = :e, updated_at = NOW() WHERE id = :id",
            [':e' => $error, ':id' => $id]
        );
    }

    /** Devolve o job à fila com backoff exponencial. */
    public function retryLater(int|string $id, int $delaySeconds, string $error): void
    {
        $this->query(
            "UPDATE jobs SET status = 'pending', available_at = :av, last_error = :e, updated_at = NOW() WHERE id = :id",
            [
                ':av' => date('Y-m-d H:i:s', time() + $delaySeconds),
                ':e'  => $error,
                ':id' => $id,
            ]
        );
    }
}
