<?php

/**
 * Router do servidor embutido (`php -S localhost:8000 router.php`).
 *
 * Em produção o Apache serve os estáticos e reescreve o resto para
 * `public/index.php`. Aqui reproduzimos isso — e há duas armadilhas que já nos
 * morderam:
 *
 * 1. O document root do `php -S` é a **raiz do projeto**, não `public/`. Então
 *    `/css/app.css` não existe no disco como `./css/app.css` — está em
 *    `public/css/app.css`. Servimos o arquivo de lá, à mão.
 *
 * 2. O `asset()` adiciona cache-busting (`app.css?v=123`). Um `preg_match` no
 *    `REQUEST_URI` cru **não casa** com a extensão por causa da query string —
 *    então o CSS caía no index.php e voltava como HTML, o navegador recusava a
 *    folha de estilo, e o app aparecia **sem estilo nenhum** no ambiente local.
 *    Por isso extraímos o path antes de testar a extensão.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . '/public' . $path;

if ($path !== '/' && is_file($file)) {
    $mimes = [
        'css'   => 'text/css',
        'js'    => 'application/javascript',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'webp'  => 'image/webp',
        'ico'   => 'image/x-icon',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'pdf'   => 'application/pdf',
    ];

    $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mime = $mimes[$ext] ?? null;

    // Sem MIME conhecido não servimos o arquivo: evita entregar .php/.env cru.
    if ($mime !== null) {
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($file));
        readfile($file);
        return true;
    }
}

require_once __DIR__ . '/public/index.php';
