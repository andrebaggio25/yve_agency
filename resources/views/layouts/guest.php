<!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(view_slot('title', 'Entrar')) ?> — <?= e(env('APP_NAME', 'YVE Agency')) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full">
<div class="flex min-h-full flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <h1 class="mt-6 text-center text-2xl font-bold tracking-tight text-gray-900">
            <?= e(env('APP_NAME', 'YVE Agency')) ?>
        </h1>
    </div>
    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
            <?= view_partial('flash') ?>
            <?= view_slot('content') ?>
        </div>
    </div>
</div>
</body>
</html>
