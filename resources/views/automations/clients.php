<?php view_layout('app'); view_start('content'); ?>

<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-xl font-semibold text-white">Automações por cliente</h1>
    <p class="text-sm text-gray-400 mt-0.5">Marque quais avisos cada cliente deve receber. Só vale para automações ativadas na agência.</p>
  </div>
  <a href="/automations" class="btn-secondary text-sm px-4 py-2">← Automações</a>
</div>

<?php if (empty($clients)): ?>
<div class="card p-12 text-center text-gray-500">Nenhum cliente cadastrado.</div>
<?php elseif (empty($clientAutomations)): ?>
<div class="card p-12 text-center text-gray-500">Nenhuma automação por cliente disponível.</div>
<?php else: ?>
<form method="POST" action="/automations/clients">
  <?= csrf_field() ?>
  <div class="card overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="border-b border-white/[0.06]">
          <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide sticky left-0 bg-[#0d0d14]">Cliente</th>
          <?php foreach ($clientAutomations as $key => $def): ?>
          <th class="px-4 py-3 text-xs font-medium text-gray-500 text-center whitespace-nowrap" title="<?= e($def['description'] ?? '') ?>">
            <?= e($def['label'] ?? $key) ?>
          </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody class="divide-y divide-white/[0.03]">
        <?php foreach ($clients as $c):
          $cid = (int) $c['id'];
          $row = $matrix[$cid] ?? [];
        ?>
        <tr class="hover:bg-white/[0.02] transition-colors">
          <td class="px-5 py-3 font-medium text-white whitespace-nowrap sticky left-0 bg-[#0d0d14]">
            <?= e($c['name']) ?>
            <?php if (empty($c['whatsapp'])): ?>
            <span class="ml-1 text-xs text-amber-500/70" title="Cliente sem WhatsApp cadastrado">⚠</span>
            <?php endif; ?>
          </td>
          <?php foreach ($clientAutomations as $key => $def): ?>
          <td class="px-4 py-3 text-center">
            <input type="checkbox" name="enabled[<?= $cid ?>][<?= e($key) ?>]" value="1"
                   <?= !empty($row[$key]) ? 'checked' : '' ?>
                   class="w-4 h-4 rounded accent-violet-500">
          </td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="flex justify-end mt-4">
    <button type="submit" class="btn-primary text-sm px-6 py-2">Salvar preferências</button>
  </div>
</form>
<?php endif; ?>

<?php view_end(); ?>
