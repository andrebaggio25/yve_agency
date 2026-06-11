<?php

declare(strict_types=1);

namespace App\Automations;

/**
 * Rotina interna (applies_to=agency): marca como vencidas as faturas em aberto
 * que passaram do vencimento. Não envia mensagem ao cliente. A UPDATE é
 * naturalmente idempotente, então dispensa automation_log.
 */
class MarkInvoicesOverdue extends AbstractAutomation
{
    protected function key(): string { return 'billing.mark_overdue'; }

    public function run(int $agencyId, array $rule): array
    {
        $stmt = $this->pdo->prepare("
            UPDATE invoices
            SET status = 'overdue', updated_at = NOW()
            WHERE agency_id = :a
              AND status IN ('sent','partial')
              AND due_date < CURRENT_DATE
        ");
        $stmt->execute([':a' => $agencyId]);

        return ['marked_overdue' => $stmt->rowCount()];
    }
}
