<?php view_layout('admin'); view_start('title'); ?><?= $user ? 'Editar usuário' : 'Novo usuário' ?><?php view_end(); ?>
<?php view_start('breadcrumb'); ?><a href="/admin/usuarios" class="hover:text-white">Usuários</a> / <?= $user ? e($user['name']) : 'Novo' ?><?php view_end(); ?>
<?php view_start('content'); ?>

<div class="max-w-2xl mb-6">
  <h1 class="text-xl font-semibold text-white"><?= $user ? 'Editar usuário' : 'Novo usuário' ?></h1>
  <?php if ($user): ?>
  <p class="text-sm text-gray-400 mt-0.5"><?= e($user['email']) ?></p>
  <?php endif; ?>
</div>

<div class="max-w-2xl space-y-5">

  <!-- Dados principais -->
  <form method="POST" action="<?= $user ? '/admin/usuarios/' . $user['id'] : '/admin/usuarios' ?>" class="card p-6 space-y-5">
    <?= csrf_field() ?>
    <?php if ($user): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>

    <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Dados do usuário</p>

    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="label-field">Nome <span class="text-red-400">*</span></label>
        <input aria-label="Nome" type="text" name="name" required autofocus
               value="<?= e($user['name'] ?? old('name')) ?>"
               class="input-field w-full">
      </div>
      <div>
        <label class="label-field">E-mail <span class="text-red-400">*</span></label>
        <input aria-label="E-mail" type="email" name="email" required
               value="<?= e($user['email'] ?? old('email')) ?>"
               class="input-field w-full">
      </div>
    </div>

    <div>
      <label class="label-field">Tenant <span class="text-red-400">*</span></label>
      <select aria-label="Tenant" name="agency_id" required class="input-field w-full">
        <option value="">Selecione o tenant...</option>
        <?php foreach ($agencies as $a): ?>
        <option value="<?= $a['id'] ?>" <?= ($user['agency_id'] ?? 0) == $a['id'] ? 'selected' : '' ?>>
          <?= e($a['name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label class="label-field">Perfis</label>
      <div class="grid grid-cols-2 gap-2 mt-1">
        <?php foreach ($roles as $role): ?>
        <label class="flex items-center gap-2.5 rounded-xl border border-white/[0.06] bg-white/[0.02] px-3 py-2.5 cursor-pointer hover:bg-white/[0.04] transition-colors">
          <input type="checkbox" name="role_ids[]" value="<?= $role['id'] ?>"
                 <?= in_array($role['id'], $userRoles ?? []) ? 'checked' : '' ?>
                 class="w-4 h-4 rounded accent-red-500">
          <div>
            <p class="text-sm text-white font-medium"><?= e($role['name']) ?></p>
            <p class="text-xs text-gray-400"><?= e($role['slug']) ?></p>
          </div>
        </label>
        <?php endforeach; ?>
        <?php if (empty($roles)): ?>
        <p class="text-sm text-gray-400 col-span-2">Nenhum perfil global disponível.</p>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!$user): ?>
    <div>
      <label class="label-field">Senha <span class="text-red-400">*</span></label>
      <input aria-label="Mínimo 8 caracteres" type="password" name="password" required minlength="8"
             placeholder="Mínimo 8 caracteres" class="input-field w-full">
    </div>
    <?php endif; ?>

    <div class="flex items-center justify-between pt-2">
      <a href="/admin/usuarios" class="btn-secondary text-sm px-4 py-2">Cancelar</a>
      <button type="submit" class="btn-primary text-sm px-6 py-2">
        <?= $user ? 'Salvar alterações' : 'Criar usuário' ?>
      </button>
    </div>
  </form>

  <?php if ($user): ?>

  <!-- Status: ativar / inativar -->
  <div class="card p-6">
    <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-4">Status da conta</p>
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm text-white font-medium">Conta <?= $user['status'] === 'active' ? 'ativa' : 'inativa' ?></p>
        <p class="text-xs text-gray-400 mt-0.5">
          <?= $user['status'] === 'active'
            ? 'O usuário pode acessar normalmente o sistema.'
            : 'O usuário está bloqueado e não pode fazer login.' ?>
        </p>
      </div>
      <form method="POST" action="/admin/usuarios/<?= $user['id'] ?>/status">
        <?= csrf_field() ?>
        <button type="submit"
                class="text-sm px-4 py-2 rounded-xl border transition-all cursor-pointer
                  <?= $user['status'] === 'active'
                    ? 'border-red-500/30 text-red-400 hover:bg-red-500/10'
                    : 'border-emerald-500/30 text-emerald-400 hover:bg-emerald-500/10' ?>">
          <?= $user['status'] === 'active' ? 'Inativar usuário' : 'Reativar usuário' ?>
        </button>
      </form>
    </div>
  </div>

  <!-- Redefinição de senha -->
  <div class="card p-6 space-y-5">
    <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Senha</p>

    <!-- Enviar link por e-mail -->
    <div class="flex items-center justify-between py-3 border-b border-white/[0.06]">
      <div>
        <p class="text-sm text-white font-medium">Enviar link de redefinição</p>
        <p class="text-xs text-gray-400 mt-0.5">Envia um e-mail com link para o usuário criar nova senha.</p>
      </div>
      <form method="POST" action="/admin/usuarios/<?= $user['id'] ?>/enviar-reset">
        <?= csrf_field() ?>
        <button type="submit" class="btn-secondary text-sm px-4 py-2">Enviar e-mail</button>
      </form>
    </div>

    <!-- Definir senha manualmente -->
    <form method="POST" action="/admin/usuarios/<?= $user['id'] ?>/senha" class="space-y-3">
      <?= csrf_field() ?>
      <div>
        <p class="text-sm text-white font-medium mb-1">Definir senha manualmente</p>
        <p class="text-xs text-gray-400 mb-3">O usuário poderá usar essa senha imediatamente.</p>
        <div class="flex gap-3">
          <input aria-label="Nova senha (mín. 8 caracteres)" type="password" name="password" required minlength="8"
                 placeholder="Nova senha (mín. 8 caracteres)"
                 class="input-field flex-1">
          <button type="submit" class="btn-primary text-sm px-4 py-2 whitespace-nowrap">Salvar senha</button>
        </div>
      </div>
    </form>
  </div>

  <?php endif; ?>
</div>

<?php view_end(); ?>
