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
     *
     * Entrega barrada pelo canal desmarcado **não** queima a idempotência
     * (BUG-07): gravar 'done' aqui faria religar o canal nunca reenviar, porque
     * o `dedupe_key` já estaria no log — a agência corrigiria a regra e nada
     * sairia. O motivo da não entrega fica no registro de entrega (timeline);
     * o `automation_log` só recebe o que de fato saiu.
     */
    protected function once(int $agencyId, ?int $clientId, string $dedupe, callable $fn, ?string $channel = null): bool
    {
        if (!$this->automations->shouldRun($this->key(), $dedupe)) {
            return false;
        }
        $fn();

        if ($channel !== null && !$this->automations->isChannelActive($agencyId, $this->key(), $channel)) {
            return false;
        }

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
