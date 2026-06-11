<?php

declare(strict_types=1);

namespace App\Automations;

/**
 * Gera a fatura do período para contratos recorrentes ativos.
 * Idempotência por contrato+período impede duplicar a fatura no mesmo ciclo.
 */
class RecurringInvoice extends AbstractAutomation
{
    protected function key(): string { return 'billing.recurring_invoice'; }

    public function run(int $agencyId, array $rule): array
    {
        $created = 0; $skipped = 0;

        $contracts = $this->select("
            SELECT * FROM contracts
            WHERE agency_id = :a AND recurring = TRUE AND status = 'active'
        ", [':a' => $agencyId]);

        foreach ($contracts as $c) {
            if (!$this->isDueThisMonth($c)) { $skipped++; continue; }

            $clientId = (int) $c['client_id'];
            if (!$this->gate($agencyId, $clientId)) { $skipped++; continue; }

            $period = date('Y-m');
            $acted = $this->once($agencyId, $clientId, "contract:{$c['id']}:invoice:{$period}", function () use ($agencyId, $c) {
                $this->createInvoice($agencyId, $c);
            });

            $acted ? $created++ : $skipped++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /** O ciclo do contrato vence neste mês? */
    private function isDueThisMonth(array $contract): bool
    {
        $recurrence  = $contract['recurrence'] ?: 'monthly';
        $startMonth  = $contract['start_date'] ? (int) date('n', strtotime((string) $contract['start_date'])) : (int) date('n');
        $currentMonth = (int) date('n');
        $diff = ($currentMonth - $startMonth + 12) % 12;

        return match ($recurrence) {
            'monthly'    => true,
            'quarterly'  => $diff % 3 === 0,
            'semiannual' => $diff % 6 === 0,
            'annual'     => $diff === 0,
            default      => true,
        };
    }

    private function createInvoice(int $agencyId, array $contract): void
    {
        $total = (float) ($contract['value'] ?? 0);
        $number = 'REC-' . $contract['id'] . '-' . date('Ym');
        $dueDate = date('Y-m-d', strtotime('+7 days'));

        $stmt = $this->pdo->prepare("
            INSERT INTO invoices
                (agency_id, client_id, contract_id, invoice_number, title, status,
                 subtotal, total, currency_code, due_date, created_at, updated_at)
            VALUES
                (:a, :c, :ct, :num, :title, 'sent',
                 :subtotal, :total, :cur, :due, NOW(), NOW())
            RETURNING id
        ");
        $stmt->execute([
            ':a'        => $agencyId,
            ':c'        => $contract['client_id'],
            ':ct'       => $contract['id'],
            ':num'      => $number,
            ':title'    => 'Mensalidade — ' . ($contract['title'] ?? 'Contrato'),
            ':subtotal' => $total,
            ':total'    => $total,
            ':cur'      => $contract['currency_code'] ?? 'BRL',
            ':due'      => $dueDate,
        ]);
        $invoiceId = (int) $stmt->fetchColumn();

        $this->pdo->prepare("
            INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total_price, sort_order, created_at)
            VALUES (:inv, :desc, 1, :price, :price, 0, NOW())
        ")->execute([
            ':inv'   => $invoiceId,
            ':desc'  => 'Serviço recorrente — ' . ($contract['title'] ?? 'Contrato'),
            ':price' => $total,
        ]);
    }
}
