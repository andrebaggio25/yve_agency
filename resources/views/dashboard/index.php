<?php view_layout('app') ?>

<?php view_start('title') ?>Dashboard<?php view_end() ?>

<?php view_start('content') ?>
<div class="space-y-6">

    <div>
        <h1 class="text-2xl font-semibold text-gray-900">
            Olá, <?= e($user['name'] ?? 'usuário') ?> 👋
        </h1>
        <p class="text-sm text-gray-500 mt-1">Visão geral da sua agência.</p>
    </div>

    <!-- Stats cards -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-5">
        <?php
        $cards = [
            ['label' => 'Clientes ativos',       'value' => $stats['active_clients'],       'color' => 'indigo'],
            ['label' => 'Planificações pendentes','value' => $stats['pending_plans'],        'color' => 'yellow'],
            ['label' => 'Aprovações pendentes',   'value' => $stats['pending_approvals'],    'color' => 'orange'],
            ['label' => 'Faturas pendentes',      'value' => $stats['pending_invoices'],     'color' => 'red'],
            ['label' => 'Alertas de campanha',    'value' => $stats['campaigns_with_alert'], 'color' => 'red'],
        ];
        foreach ($cards as $card):
        ?>
        <div class="bg-white rounded-lg shadow p-5">
            <p class="text-sm text-gray-500"><?= e($card['label']) ?></p>
            <p class="mt-1 text-3xl font-semibold text-gray-900"><?= e($card['value']) ?></p>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Placeholder para gráficos (Fase 5) -->
    <div class="bg-white rounded-lg shadow p-6 text-center text-gray-400 text-sm">
        Gráficos e métricas em tempo real estarão disponíveis na Fase 5.
    </div>

</div>
<?php view_end() ?>
