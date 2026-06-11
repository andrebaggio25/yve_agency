<?php

declare(strict_types=1);

namespace App\Automations;

use App\Core\Database;
use App\Services\AutomationService;
use App\Services\NotificationService;
use PDO;

/**
 * Base dos handlers. Roda no contexto do worker/cron (sem sessão), então tudo
 * consulta com agency_id explícito. Centraliza o gate (opt-in) + idempotência.
 */
abstract class AbstractAutomation implements AutomationHandler
{
    protected PDO $pdo;

    public function __construct(
        protected readonly AutomationService  $automations,
        protected readonly NotificationService $notifications,
    ) {
        $this->pdo = Database::connection();
    }

    /** Chave da automação (igual à do catálogo config/automations.php). */
    abstract protected function key(): string;

    /** A automação está habilitada para este cliente (agência ativa + opt-in)? */
    protected function gate(int $agencyId, ?int $clientId): bool
    {
        return $this->automations->isEnabledForClient($agencyId, $clientId, $this->key());
    }

    /**
     * Executa $fn uma única vez por dedupe (idempotência). Retorna true se executou.
     */
    protected function once(int $agencyId, ?int $clientId, string $dedupe, callable $fn, ?string $channel = null): bool
    {
        if (!$this->automations->shouldRun($this->key(), $dedupe)) {
            return false;
        }
        $fn();
        $this->automations->markRan($agencyId, $clientId, $this->key(), $dedupe, 'done', $channel);
        return true;
    }

    /** Helper de consulta. @return array<int,array<string,mixed>> */
    protected function select(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    protected function client(int $agencyId, int $clientId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM clients WHERE id = :id AND agency_id = :a LIMIT 1");
        $stmt->execute([':id' => $clientId, ':a' => $agencyId]);
        return $stmt->fetch() ?: null;
    }
}
