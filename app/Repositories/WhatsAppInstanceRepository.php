<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Gerencia a tabela whatsapp_instances (1 instância por agência).
 * Não usa o Repository base pois o scope é por agency_id explícito.
 */
class WhatsAppInstanceRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function findByAgency(int $agencyId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM whatsapp_instances WHERE agency_id = :agency_id LIMIT 1'
        );
        $stmt->execute([':agency_id' => $agencyId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByWebhookToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM whatsapp_instances WHERE webhook_token = :token LIMIT 1'
        );
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $now  = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare("
            INSERT INTO whatsapp_instances
                (agency_id, name, instance_name, webhook_token, status, phone_connected, created_at, updated_at)
            VALUES
                (:agency_id, :name, :instance_name, :webhook_token, 'pending', FALSE, :now, :now)
            RETURNING id
        ");
        $stmt->execute([
            ':agency_id'     => $data['agency_id'],
            ':name'          => $data['name'] ?? 'Principal',
            ':instance_name' => $data['instance_name'],
            ':webhook_token' => $data['webhook_token'],
            ':now'           => $now,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function updateStatus(int $id, string $status, ?string $phone = null, bool $connected = false): void
    {
        $this->pdo->prepare("
            UPDATE whatsapp_instances
            SET status = :status, phone_number = :phone, phone_connected = :connected, updated_at = NOW()
            WHERE id = :id
        ")->execute([
            ':status'    => $status,
            ':phone'     => $phone,
            ':connected' => $connected ? 1 : 0,
            ':id'        => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM whatsapp_instances WHERE id = :id')
            ->execute([':id' => $id]);
    }

    public function deleteByAgency(int $agencyId): void
    {
        $this->pdo->prepare('DELETE FROM whatsapp_instances WHERE agency_id = :agency_id')
            ->execute([':agency_id' => $agencyId]);
    }

    /** All instances (for platform admin) */
    public function findAll(): array
    {
        return $this->pdo->query("
            SELECT wi.*, a.name AS agency_name
            FROM whatsapp_instances wi
            JOIN agencies a ON a.id = wi.agency_id
            ORDER BY a.name
        ")->fetchAll();
    }
}
