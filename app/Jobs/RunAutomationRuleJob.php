<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Core\Container;
use App\Repositories\AutomationRepository;
use App\Services\AutomationService;

/**
 * Enfileirado pelo scheduler (/queue/scheduler ou bin/scheduler.php), consumido
 * pelo worker (/queue/work ou bin/worker.php). Resolve o handler do catálogo e
 * executa a automação para a agência da regra.
 *
 * Instanciado com `new RunAutomationRuleJob()` (sem DI), então resolve suas
 * dependências via Container::getInstance() (autowiring por reflection).
 */
class RunAutomationRuleJob
{
    public function handle(array $data): void
    {
        $ruleId = (int) ($data['rule_id'] ?? 0);
        if ($ruleId <= 0) return;

        $repo = new AutomationRepository();
        $rule = $repo->findRuleById($ruleId);
        if (!$rule || ($rule['status'] ?? '') !== 'active') return;

        $automations = Container::getInstance()->make(AutomationService::class);
        $def = $automations->definition($rule['automation_key']);
        $handlerClass = $def['handler'] ?? null;
        if (!$handlerClass || !class_exists($handlerClass)) return;

        $handler = Container::getInstance()->make($handlerClass);
        $handler->run((int) $rule['agency_id'], $rule);
    }
}
