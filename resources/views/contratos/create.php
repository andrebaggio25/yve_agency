<?php view_layout('app'); view_start('content'); ?>
<div class="max-w-3xl mx-auto">
  <div class="mb-8">
    <a href="/contratos" class="text-xs text-gray-500 hover:text-gray-300 transition-colors">← Contratos</a>
    <h1 class="text-2xl font-bold text-white mt-2">Novo Contrato</h1>
  </div>
  <div class="rounded-2xl border border-white/5 bg-white/[0.03] p-6">
    <?php include __DIR__ . '/_form.php'; ?>
  </div>
</div>
<?php view_end(); ?>
