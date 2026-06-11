<?php

declare(strict_types=1);

/**
 * Catálogo canônico de automações.
 *
 * Cada entrada descreve UMA automação. O super_admin da agência ativa/agenda
 * cada uma em /automations; automações `applies_to = 'client'` ainda exigem
 * opt-in por cliente (matriz /automations/clients ou ficha do cliente).
 *
 * Campos:
 *  - label / description : exibidos na UI
 *  - trigger     : 'schedule' (varrido pelo /queue/scheduler) | 'event' (disparado inline por um Service)
 *  - applies_to  : 'client' (opt-in por cliente) | 'agency' (toggle único)
 *  - frequency   : 'daily' | 'weekly' | 'monthly' (só para trigger=schedule)
 *  - day         : dia padrão para weekly (ex.: 'Monday') / monthly ('1')
 *  - time        : HH:MM padrão de execução
 *  - channels    : canais sugeridos ['whatsapp','email','inapp']
 *  - handler     : classe App\Automations\* (só para trigger=schedule)
 */

return [

    // ── Financeiro ──────────────────────────────────────────────────────────
    'billing.invoice_due_reminder' => [
        'label'       => 'Lembrete de fatura a vencer',
        'description' => 'Avisa o cliente 3 dias antes do vencimento de uma fatura em aberto.',
        'trigger'     => 'schedule',
        'applies_to'  => 'client',
        'frequency'   => 'daily',
        'time'        => '08:00',
        'channels'    => ['whatsapp', 'email'],
        'handler'     => \App\Automations\InvoiceDueReminder::class,
    ],
    'billing.invoice_overdue' => [
        'label'       => 'Lembrete de fatura vencida',
        'description' => 'Avisa o cliente quando a fatura vence e nos dias seguintes enquanto não for paga.',
        'trigger'     => 'schedule',
        'applies_to'  => 'client',
        'frequency'   => 'daily',
        'time'        => '09:00',
        'channels'    => ['whatsapp', 'email'],
        'handler'     => \App\Automations\InvoiceOverdueReminder::class,
    ],
    'billing.recurring_invoice' => [
        'label'       => 'Gerar fatura recorrente',
        'description' => 'Gera automaticamente a fatura do mês para contratos recorrentes ativos.',
        'trigger'     => 'schedule',
        'applies_to'  => 'client',
        'frequency'   => 'daily',
        'time'        => '06:00',
        'channels'    => ['email'],
        'handler'     => \App\Automations\RecurringInvoice::class,
    ],
    'billing.mark_overdue' => [
        'label'       => 'Marcar faturas vencidas',
        'description' => 'Rotina interna: marca como vencidas as faturas que passaram do vencimento. Não envia mensagem ao cliente.',
        'trigger'     => 'schedule',
        'applies_to'  => 'agency',
        'frequency'   => 'daily',
        'time'        => '00:30',
        'channels'    => [],
        'handler'     => \App\Automations\MarkInvoicesOverdue::class,
    ],

    // ── Conteúdo & aprovação ────────────────────────────────────────────────
    'content.approval_reminder' => [
        'label'       => 'Lembrete de aprovação de conteúdo',
        'description' => 'Cobra o cliente quando um plano está aguardando aprovação há mais de 2 dias.',
        'trigger'     => 'schedule',
        'applies_to'  => 'client',
        'frequency'   => 'daily',
        'time'        => '10:00',
        'channels'    => ['whatsapp', 'email'],
        'handler'     => \App\Automations\ApprovalReminder::class,
    ],
    'content.approval_escalation' => [
        'label'       => 'Escalonar aprovação parada',
        'description' => 'Avisa o gestor (in-app) quando um plano segue sem aprovação após 5 dias.',
        'trigger'     => 'schedule',
        'applies_to'  => 'agency',
        'frequency'   => 'daily',
        'time'        => '11:00',
        'channels'    => ['inapp'],
        'handler'     => \App\Automations\ApprovalEscalation::class,
    ],
    'content.approved_create_tasks' => [
        'label'       => 'Criar tarefas ao aprovar plano',
        'description' => 'Quando o cliente aprova um plano, cria automaticamente as tarefas de produção para a equipe.',
        'trigger'     => 'event',
        'applies_to'  => 'agency',
        'channels'    => ['inapp'],
    ],

    // ── Contratos / tarefas / clientes ──────────────────────────────────────
    'contract.expiring' => [
        'label'       => 'Alerta de contrato expirando',
        'description' => 'Avisa o gestor (in-app) quando um contrato ativo vence nos próximos 30 dias.',
        'trigger'     => 'schedule',
        'applies_to'  => 'agency',
        'frequency'   => 'daily',
        'time'        => '07:00',
        'channels'    => ['inapp'],
        'handler'     => \App\Automations\ContractExpiring::class,
    ],
    'task.sla_overdue' => [
        'label'       => 'Alerta de tarefa atrasada',
        'description' => 'Avisa o responsável (in-app) quando uma tarefa passa do prazo sem ser concluída.',
        'trigger'     => 'schedule',
        'applies_to'  => 'agency',
        'frequency'   => 'daily',
        'time'        => '07:30',
        'channels'    => ['inapp'],
        'handler'     => \App\Automations\TaskSlaOverdue::class,
    ],
    'client.onboarding' => [
        'label'       => 'Onboarding automático de cliente',
        'description' => 'Ao cadastrar um novo cliente, gera o acesso ao portal e envia a mensagem de boas-vindas.',
        'trigger'     => 'event',
        'applies_to'  => 'agency',
        'channels'    => ['whatsapp', 'email'],
    ],

    // ── Relatórios & insights ───────────────────────────────────────────────
    'report.client_monthly' => [
        'label'       => 'Relatório mensal ao cliente',
        'description' => 'No início de cada mês, envia ao cliente o link do relatório executivo.',
        'trigger'     => 'schedule',
        'applies_to'  => 'client',
        'frequency'   => 'monthly',
        'day'         => '1',
        'time'        => '08:00',
        'channels'    => ['email'],
        'handler'     => \App\Automations\ClientMonthlyReport::class,
    ],
    'digest.team_daily' => [
        'label'       => 'Resumo diário da equipe',
        'description' => 'Envia um resumo (in-app) com o que vence hoje, aprovações pendentes e faturas a receber.',
        'trigger'     => 'schedule',
        'applies_to'  => 'agency',
        'frequency'   => 'daily',
        'time'        => '07:00',
        'channels'    => ['inapp'],
        'handler'     => \App\Automations\TeamDailyDigest::class,
    ],
];
