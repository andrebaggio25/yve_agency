<!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title><?= e(view_slot('title', 'YVE Agency')) ?> — <?= e(env('APP_NAME', 'YVE Agency')) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <?= view_slot('head') ?>
</head>
<body class="h-full" x-data>

<!-- Sidebar -->
<div class="flex h-full">
    <?= view_partial('nav') ?>

    <!-- Main content -->
    <div class="flex flex-1 flex-col overflow-hidden">

        <!-- Top bar -->
        <header class="bg-white border-b border-gray-200 px-6 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="text-sm text-gray-500"><?= e(view_slot('breadcrumb', '')) ?></span>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm text-gray-700"><?= e(\App\Support\Auth::user()['name'] ?? '') ?></span>
                <form method="POST" action="/logout">
                    <?= csrf_field() ?>
                    <button type="submit" class="text-sm text-gray-500 hover:text-gray-800">Sair</button>
                </form>
            </div>
        </header>

        <!-- Flash messages -->
        <?= view_partial('flash') ?>

        <!-- Page content -->
        <main class="flex-1 overflow-y-auto p-6">
            <?= view_slot('content') ?>
        </main>

    </div>
</div>

<?= view_slot('scripts') ?>
</body>
</html>
