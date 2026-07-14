<?php view_layout('app') ?>
<?php view_start('title') ?>Dashboard<?php view_end() ?>

<?php view_start('content') ?>
<div class="space-y-8">

  <div>
    <p class="text-xs font-semibold uppercase tracking-widest text-brand-500 mb-1">Dashboard</p>
    <h1 class="text-2xl font-bold text-white">
      Olá, <?= e(explode(' ', $user['name'] ?? 'usuário')[0]) ?>
    </h1>
    <p class="text-sm text-gray-400 mt-1"><?= e(date_long()) ?></p>
  </div>

  <!-- Stats -->
  <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
    <?php
    $statCards = [
      [
        'label' => 'Clientes ativos',
        'value' => $stats['active_clients'],
        'href'  => '/clientes',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>',
        'color' => 'text-brand-400 bg-brand-500/10',
      ],
      [
        'label' => 'Planos em andamento',
        'value' => $stats['pending_plans'],
        'href'  => '/conteudo',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>',
        'color' => 'text-blue-400 bg-blue-500/10',
      ],
      [
        'label' => 'Aguardando aprovação',
        'value' => $stats['pending_approvals'],
        'href'  => '/aprovacoes',
        'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>',
        'color' => 'text-amber-400 bg-amber-500/10',
      ],
    ];
    foreach ($statCards as $card):
    ?>
    <a href="<?= $card['href'] ?>"
       class="group flex items-center gap-4 rounded-2xl border border-white/5 bg-white/[0.03] p-5 hover:border-brand-500/20 hover:bg-white/[0.05] transition-all">
      <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl <?= $card['color'] ?>">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?= $card['icon'] ?></svg>
      </div>
      <div>
        <p class="text-2xl font-bold text-white"><?= $card['value'] ?></p>
        <p class="text-sm text-gray-400"><?= $card['label'] ?></p>
      </div>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Financial widget -->
  <?php if (!empty($financialSummary) && \App\Support\Auth::canAny('invoices.view','contracts.view')): ?>
  <?php $fs = $financialSummary; ?>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-sm font-semibold uppercase tracking-widest text-gray-400">Financeiro</h2>
      <a href="/financeiro" class="text-xs text-brand-400 hover:text-brand-300 transition-colors">Ver mais →</a>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
      <?php
      $fw = [
        ['Faturado',   'R$ '.number_format((float)($fs['billed_total']??0),2,',','.'),  'text-white'],
        ['Recebido',   'R$ '.number_format((float)($fs['received_total']??0),2,',','.'), 'text-emerald-400'],
        ['A Receber',  'R$ '.number_format((float)($fs['pending_total']??0),2,',','.'),  'text-amber-400'],
        ['Vencidas',   (int)($fs['overdue']??0).' fatura'.((int)($fs['overdue']??0)!==1?'s':''), ($fs['overdue']??0)>0?'text-red-400':'text-gray-400'],
      ];
      foreach ($fw as [$lbl,$val,$cls]): ?>
      <div class="rounded-2xl border border-white/5 bg-white/[0.03] px-4 py-3">
        <p class="text-xs text-gray-400 mb-0.5"><?= $lbl ?></p>
        <p class="font-semibold text-sm <?= $cls ?>"><?= $val ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Recent plans -->
  <?php if (!empty($recent_plans)): ?>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-sm font-semibold uppercase tracking-widest text-gray-400">Planos recentes</h2>
      <a href="/conteudo" class="text-xs text-brand-400 hover:text-brand-300 transition-colors">Ver todos →</a>
    </div>
    <div class="rounded-2xl border border-white/5 bg-white/[0.03] overflow-hidden">
      <?php foreach ($recent_plans as $i => $plan): ?>
      <a href="/conteudo/<?= e($plan['id']) ?>"
         class="flex items-center justify-between px-5 py-3.5 hover:bg-white/[0.03] transition-colors <?= $i > 0 ? 'border-t border-white/5' : '' ?>">
        <div class="flex items-center gap-3">
          <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-500/10 text-xs font-bold text-brand-300 shrink-0">
            <?= strtoupper(substr($plan['client_name'], 0, 2)) ?>
          </div>
          <div>
            <p class="text-sm font-medium text-white"><?= e($plan['title']) ?></p>
            <p class="text-xs text-gray-400"><?= e($plan['client_name']) ?> · <?= date_fmt($plan['week_start'], 'd/m/Y') ?></p>
          </div>
        </div>
        <?php
          $statusMap = [
            'draft'    => ['text-gray-400',   'Rascunho'],
            'sent'     => ['text-blue-300',    'Enviado'],
            'approved' => ['text-emerald-300', 'Aprovado'],
            'revision' => ['text-amber-300',   'Revisão'],
          ];
          [$cls, $label] = $statusMap[$plan['status']] ?? ['text-gray-400', $plan['status']];
        ?>
        <span class="text-xs font-semibold <?= $cls ?>"><?= $label ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php else: ?>
  <div class="rounded-2xl border border-dashed border-white/10 p-10 text-center">
    <p class="text-gray-400 text-sm">Nenhum plano criado ainda.</p>
    <a href="/conteudo/novo" class="mt-3 inline-block text-sm text-brand-400 hover:text-brand-300 transition-colors">
      Criar primeiro plano →
    </a>
  </div>
  <?php endif; ?>

</div>
<?php view_end() ?>
