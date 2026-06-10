<?php
$success = flash('success');
$error   = flash('error');
$errors  = flash('errors');
?>
<?php if ($success): ?>
<div class="mx-6 mt-4 rounded-md bg-green-50 border border-green-200 p-4">
    <div class="flex">
        <svg class="h-5 w-5 text-green-400 shrink-0" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
        </svg>
        <p class="ml-3 text-sm text-green-800"><?= e($success) ?></p>
    </div>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="mx-6 mt-4 rounded-md bg-red-50 border border-red-200 p-4">
    <div class="flex">
        <svg class="h-5 w-5 text-red-400 shrink-0" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
        </svg>
        <p class="ml-3 text-sm text-red-800"><?= e($error) ?></p>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($errors) && is_array($errors)): ?>
<div class="mx-6 mt-4 rounded-md bg-red-50 border border-red-200 p-4">
    <h3 class="text-sm font-medium text-red-800">Corrija os erros abaixo:</h3>
    <ul class="mt-2 list-disc list-inside space-y-1">
        <?php foreach ($errors as $field => $msg): ?>
        <li class="text-sm text-red-700"><?= e($msg) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
