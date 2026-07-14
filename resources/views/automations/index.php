<?php view_layout('app'); view_start('content'); ?>

<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-xl font-semibold text-white">Automações</h1>
    <p class="text-sm text-gray-400 mt-0.5">Ative as automações da agência e configure a agenda. Para ligar por cliente, use as preferências por cliente.</p>
  </div>
  <div class="flex items-center gap-2 flex-wrap">
    <!-- OBS-02: automação que ninguém vê acontecer parece que não existe -->
    <a href="/automations/deliveries" class="btn-secondary text-sm px-4 py-2">Ver entregas →</a>
    <a href="/automations/clients" class="btn-secondary text-sm px-4 py-2">Preferências por cliente →</a>
  </div>
</div>

<div class="space-y-3">
  <?php foreach ($rules as $key => $r):
    $isEvent  = ($r['trigger'] ?? 'schedule') === 'event';
    $isClient = ($r['applies_to'] ?? 'agency') === 'client';
    $active   = ($r['status'] ?? 'inactive') === 'active';
    $time     = substr((string) ($r['scheduled_time'] ?? '08:00'), 0, 5);
    $channels = $r['channels_on'] ?? [];
  ?>
  <form method="POST" action="/automations/<?= e($key) ?>" class="card p-5">
    <?= csrf_field() ?>
    <?= method_field('PUT') ?>

    <div class="flex items-start justify-between gap-4">
      <div class="min-w-0">
        <div class="flex items-center gap-2 flex-wrap">
          <h2 class="text-sm font-semibold text-white"><?= e($r['label'] ?? $key) ?></h2>
          <span class="badge <?= $isClient ? 'bg-violet-500/10 text-violet-300' : 'bg-blue-500/10 text-blue-300' ?>">
            <?= $isClient ? 'por cliente' : 'agência' ?>
          </span>
          <span class="badge <?= $isEvent ? 'bg-amber-500/10 text-amber-300' : 'bg-emerald-500/10 text-emerald-300' ?>">
            <?= $isEvent ? 'evento' : 'agendada' ?>
          </span>
        </div>
        <p class="text-xs text-gray-500 mt-1"><?= e($r['description'] ?? '') ?></p>
      </div>

      <label class="flex items-center gap-2 cursor-pointer flex-shrink-0">
        <input type="checkbox" name="status" value="active" <?= $active ? 'checked' : '' ?>
               class="w-4 h-4 rounded accent-violet-500">
        <span class="text-sm text-gray-300">Ativa</span>
      </label>
    </div>

    <div class="flex flex-wrap items-end gap-4 mt-4">
      <?php if (!$isEvent): ?>
        <div>
          <label class="label-field">Horário</label>
          <input type="time" name="time" value="<?= e($time) ?>" class="input-field py-1.5">
        </div>
        <?php if (($r['frequency'] ?? '') === 'weekly'): ?>
        <div>
          <label class="label-field">Dia da semana</label>
          <select name="day" class="input-field py-1.5">
            <?php foreach (['Monday'=>'Segunda','Tuesday'=>'Terça','Wednesday'=>'Quarta','Thursday'=>'Quinta','Friday'=>'Sexta','Saturday'=>'Sábado','Sunday'=>'Domingo'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= ($r['scheduled_day'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php elseif (($r['frequency'] ?? '') === 'monthly'): ?>
        <div>
          <label class="label-field">Dia do mês</label>
          <input type="number" name="day" min="1" max="28" value="<?= e($r['scheduled_day'] ?? '1') ?>" class="input-field py-1.5 w-20">
        </div>
        <?php endif; ?>
        <div>
          <span class="label-field">Frequência</span>
          <p class="text-sm text-gray-400 py-1.5">
            <?= ['daily'=>'Diária','weekly'=>'Semanal','monthly'=>'Mensal'][$r['frequency'] ?? ''] ?? '—' ?>
          </p>
        </div>
      <?php else: ?>
        <p class="text-xs text-gray-500">Disparada automaticamente pelo evento correspondente.</p>
      <?php endif; ?>

      <?php if (!empty($r['channels'])): ?>
      <div>
        <span class="label-field">Canais</span>
        <div class="flex items-center gap-3 py-1.5">
          <?php foreach ($r['channels'] as $ch):
            $labels = ['whatsapp'=>'WhatsApp','email'=>'E-mail','inapp'=>'No sistema'];
          ?>
          <label class="flex items-center gap-1.5 cursor-pointer">
            <input type="checkbox" name="channels[]" value="<?= e($ch) ?>" <?= in_array($ch, $channels, true) ? 'checked' : '' ?>
                   class="w-3.5 h-3.5 rounded accent-violet-500">
            <span class="text-xs text-gray-400"><?= $labels[$ch] ?? $ch ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="ml-auto">
        <button type="submit" class="btn-primary text-sm px-4 py-2">Salvar</button>
      </div>
    </div>
  </form>
  <?php endforeach; ?>
</div>

<?php view_end(); ?>
