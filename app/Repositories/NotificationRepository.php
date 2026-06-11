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

    // ── Job queue ─────────────────────────────────────────────────────────────

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

    public function pendingJobs(int $limit = 10): array
    {
        $safeLimit = max(1, (int) $limit);
        return $this->all(
            "SELECT * FROM notification_jobs
             WHERE status = 'pending' AND (next_try_at IS NULL OR next_try_at <= NOW())
             ORDER BY created_at ASC LIMIT {$safeLimit}",
        );
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
