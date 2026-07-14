<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

class NotificationRepository extends Repository
{
    protected string $table = 'notifications';

    // ── In-app notifications ──────────────────────────────────────────────────

    public function createNotification(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO notifications (agency_id, user_id, type, title, body, action_url, created_at)
             VALUES (:agency_id, :user_id, :type, :title, :body, :action_url, NOW())
             RETURNING id'
        );
        $stmt->execute([
            ':agency_id'  => $data['agency_id'],
            ':user_id'    => $data['user_id'],
            ':type'       => $data['type'],
            ':title'      => $data['title'],
            ':body'       => $data['body'] ?? null,
            ':action_url' => $data['action_url'] ?? null,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function unreadForUser(int $userId, int $agencyId, int $limit = 20): array
    {
        $safeLimit = max(1, (int) $limit);
        return $this->all(
            "SELECT * FROM notifications
             WHERE user_id = :user_id AND agency_id = :agency_id AND read_at IS NULL
             ORDER BY created_at DESC LIMIT {$safeLimit}",
            [':user_id' => $userId, ':agency_id' => $agencyId],
        );
    }

    public function unreadCount(int $userId, int $agencyId): int
    {
        return (int) $this->scalar(
            'SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND agency_id = :aid AND read_at IS NULL',
            [':uid' => $userId, ':aid' => $agencyId],
        );
    }

    public function markRead(int $notificationId, int $userId): void
    {
        $this->query(
            "UPDATE notifications SET read_at = NOW() WHERE id = :id AND user_id = :uid AND read_at IS NULL",
            [':id' => $notificationId, ':uid' => $userId],
        );
    }

    public function markAllRead(int $userId, int $agencyId): void
    {
        $this->query(
            "UPDATE notifications SET read_at = NOW() WHERE user_id = :uid AND agency_id = :aid AND read_at IS NULL",
            [':uid' => $userId, ':aid' => $agencyId],
        );
    }

    // ── Registro de entrega (antes: fila própria — ver INFRA-01) ─────────────
    //
    // `notification_jobs` deixou de ser fila: a fila real agora é a `jobs`
    // (SKIP LOCKED + backoff + alerta). Aqui fica o HISTÓRICO de entrega — o que
    // foi enviado, pra quem, por qual canal, com que resultado. É a fonte da
    // timeline do OBS-02 e do "a cliente diz que não recebeu" do suporte.

    /** Registra a entrega a ser feita (status `pending`) e devolve o ID. */
    public function enqueue(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO notification_jobs
             (agency_id, channel, recipient, template, locale, payload, status, attempts, next_try_at, created_at)
             VALUES (:agency_id, :channel, :recipient, :template, :locale, :payload, :status, 0, NOW(), NOW())
             RETURNING id'
        );
        $stmt->execute([
            ':agency_id' => $data['agency_id'],
            ':channel'   => $data['channel'],
            ':recipient' => $data['recipient'],
            ':template'  => $data['template'],
            ':locale'    => $data['locale'] ?? 'pt',
            ':payload'   => json_encode($data['payload'] ?? []),
            ':status'    => 'pending',
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function findDelivery(int $id): ?array
    {
        return $this->first("SELECT * FROM notification_jobs WHERE id = :id LIMIT 1", [':id' => $id]);
    }

    /**
     * Entregas pendentes SEM job correspondente na fila `jobs`.
     * Existe para o resgate do legado (INFRA-01): registros enfileirados pela
     * fila antiga que ficariam órfãos após a migração.
     */
    public function orphanPendingDeliveries(int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT nj.* FROM notification_jobs nj
             WHERE nj.status = 'pending'
               AND NOT EXISTS (
                   SELECT 1 FROM jobs j
                   WHERE j.queue = 'notifications'
                     AND j.status IN ('pending', 'reserved')
                     AND j.payload::jsonb -> 'data' ->> 'notification_id' = nj.id::text
               )
             ORDER BY nj.created_at ASC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Histórico de entregas da agência (OBS-02): o que saiu, pra quem, quando.
     * @param array{channel?:string,status?:string} $filters
     */
    public function deliveriesByAgency(int $agencyId, array $filters = [], int $limit = 100): array
    {
        $where  = ['agency_id = :aid'];
        $params = [':aid' => $agencyId];

        if (!empty($filters['channel'])) {
            $where[]           = 'channel = :ch';
            $params[':ch']     = $filters['channel'];
        }
        if (!empty($filters['status'])) {
            $where[]           = 'status = :st';
            $params[':st']     = $filters['status'];
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM notification_jobs WHERE ' . implode(' AND ', $where) .
            ' ORDER BY created_at DESC LIMIT :lim'
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** Contadores por status (cabeçalho da timeline). */
    public function deliveryStats(int $agencyId, int $days = 30): array
    {
        $row = $this->first(
            "SELECT
                COUNT(*)                                  AS total,
                COUNT(*) FILTER (WHERE status = 'sent')    AS sent,
                COUNT(*) FILTER (WHERE status = 'failed')  AS failed,
                COUNT(*) FILTER (WHERE status = 'pending') AS pending
             FROM notification_jobs
             WHERE agency_id = :aid AND created_at >= NOW() - (:d || ' days')::interval",
            [':aid' => $agencyId, ':d' => (string) $days]
        ) ?? [];

        return [
            'total'   => (int) ($row['total']   ?? 0),
            'sent'    => (int) ($row['sent']    ?? 0),
            'failed'  => (int) ($row['failed']  ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
        ];
    }

    /**
     * INT-02 — quando o último WhatsApp desta agência está agendado.
     * Serve para espaçar os envios: WhatsApp bane número que dispara em rajada,
     * e o número é o telefone da agência, não um recurso descartável.
     */
    public function lastScheduledWhatsAppAt(int $agencyId): ?string
    {
        $row = $this->first(
            "SELECT MAX(j.available_at) AS last_at
             FROM jobs j
             WHERE j.agency_id = :aid
               AND j.queue = 'notifications'
               AND j.status = 'pending'
               AND j.payload::jsonb -> 'data' ->> 'channel' = 'whatsapp'",
            [':aid' => $agencyId]
        );

        return $row['last_at'] ?? null;
    }

    public function markJobSent(int $jobId): void
    {
        $this->query(
            "UPDATE notification_jobs SET status = 'sent', sent_at = NOW() WHERE id = :id",
            [':id' => $jobId],
        );
    }

    public function markJobFailed(int $jobId, string $error, int $retryAfterSeconds = 300): void
    {
        $this->query(
            "UPDATE notification_jobs
             SET status = CASE WHEN attempts >= 3 THEN 'failed' ELSE 'pending' END,
                 attempts   = attempts + 1,
                 last_error = :error,
                 next_try_at = NOW() + INTERVAL '{$retryAfterSeconds} seconds'
             WHERE id = :id",
            [':error' => $error, ':id' => $jobId],
        );
    }

    // ── WhatsApp instance ────────────────────────────────────────────────────

    public function findInstance(int $agencyId): ?array
    {
        return $this->first(
            'SELECT * FROM whatsapp_instances WHERE agency_id = :agency_id LIMIT 1',
            [':agency_id' => $agencyId],
        );
    }

    public function upsertInstance(int $agencyId, array $data): void
    {
        $existing = $this->findInstance($agencyId);
        $now      = date('Y-m-d H:i:s');

        if ($existing) {
            $this->query(
                'UPDATE whatsapp_instances SET instance_name = :name, base_url = :url, api_key = :key,
                 status = :status, updated_at = :now WHERE agency_id = :agency_id',
                [
                    ':name'      => $data['instance_name'],
                    ':url'       => $data['base_url'],
                    ':key'       => $data['api_key'],
                    ':status'    => $data['status'] ?? 'disconnected',
                    ':now'       => $now,
                    ':agency_id' => $agencyId,
                ],
            );
        } else {
            $this->query(
                'INSERT INTO whatsapp_instances (agency_id, instance_name, base_url, api_key, status, created_at, updated_at)
                 VALUES (:agency_id, :name, :url, :key, :status, :now, :now)',
                [
                    ':agency_id' => $agencyId,
                    ':name'      => $data['instance_name'],
                    ':url'       => $data['base_url'],
                    ':key'       => $data['api_key'],
                    ':status'    => $data['status'] ?? 'disconnected',
                    ':now'       => $now,
                ],
            );
        }
    }

    public function updateInstanceStatus(int $agencyId, string $status, ?string $phone = null): void
    {
        $this->query(
            'UPDATE whatsapp_instances SET status = :status, phone_number = :phone, updated_at = NOW()
             WHERE agency_id = :agency_id',
            [':status' => $status, ':phone' => $phone, ':agency_id' => $agencyId],
        );
    }

    // ── Helper ───────────────────────────────────────────────────────────────

    private function scalar(string $sql, array $params = []): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
