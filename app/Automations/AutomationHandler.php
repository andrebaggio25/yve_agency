<?php

declare(strict_types=1);

namespace App\Automations;

interface AutomationHandler
{
    /**
     * Executa a automação para uma agência.
     * @param array $rule  Linha de automation_rules (status, scheduled_time, channels...)
     * @return array       Estatísticas livres (ex.: ['sent' => 3, 'skipped' => 1])
     */
    public function run(int $agencyId, array $rule): array;
}
