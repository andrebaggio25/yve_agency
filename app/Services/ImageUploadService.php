<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Upload de imagem pública (logo da agência/cliente).
 *
 * Antes o campo pedia uma **URL** — o que obrigava a pessoa a hospedar a
 * imagem em outro lugar antes de usar o sistema. Pior: uma URL externa quebra
 * quando o host de fora sai do ar, e o logo do portal do cliente simplesmente
 * some.
 *
 * Cuidados obrigatórios (o arquivo vira URL pública no nosso domínio):
 * - MIME validado **pelo conteúdo** (finfo), nunca pelo que o navegador diz;
 * - **SVG é recusado**: é XML com script — um SVG hostil servido do nosso
 *   domínio é XSS armazenado;
 * - nome gerado por nós (aleatório + extensão da allowlist), então nada de
 *   `logo.php` virando executável;
 * - tamanho limitado — logo é logo, não um vídeo.
 */
class ImageUploadService
{
    private const MAX_BYTES = 2 * 1024 * 1024; // 2 MB

    /** MIME (por conteúdo) → extensão que vamos usar. SVG fora, de propósito. */
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    /**
     * Salva o upload e devolve a URL pública (ex.: `/uploads/logos/ag7-a1b2.png`).
     *
     * @param array $file Item de `$_FILES`
     * @throws RuntimeException com mensagem já pronta para o usuário
     */
    public function storeLogo(array $file, string $prefix = 'logo'): string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new RuntimeException('A imagem excede o tamanho máximo permitido pelo servidor.');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Falha ao enviar a imagem.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');

        // is_uploaded_file: garante que o caminho veio de um upload HTTP, e não
        // de um caminho arbitrário forjado no request.
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Arquivo inválido.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new RuntimeException('A imagem deve ter até 2 MB.');
        }

        $mime = $this->detectMime($tmp);
        if (!isset(self::ALLOWED[$mime])) {
            throw new RuntimeException('Formato não suportado. Envie PNG, JPG, WEBP ou GIF.');
        }

        $ext      = self::ALLOWED[$mime];
        $name     = $this->slug($prefix) . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
        $dir      = public_path('uploads/logos');
        $destPath = $dir . DIRECTORY_SEPARATOR . $name;

        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Não foi possível preparar a pasta de uploads.');
        }

        if (!move_uploaded_file($tmp, $destPath)) {
            throw new RuntimeException('Não foi possível salvar a imagem.');
        }

        @chmod($destPath, 0644); // legível pelo servidor web, nunca executável

        return '/uploads/logos/' . $name;
    }

    /** Remove um logo antigo — só dentro da nossa pasta (nunca um caminho de fora). */
    public function deleteLogo(?string $url): void
    {
        if (!$url || !str_starts_with($url, '/uploads/logos/')) {
            return; // URL externa (legado) ou vazia: não é nossa para apagar
        }

        $path = public_path(ltrim($url, '/'));
        $real = realpath($path);
        $base = realpath(public_path('uploads/logos'));

        // Impede path traversal ("/uploads/logos/../../index.php").
        if ($real && $base && str_starts_with($real, $base) && is_file($real)) {
            @unlink($real);
        }
    }

    private function detectMime(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return '';
        }

        $mime = (string) finfo_file($finfo, $path);
        finfo_close($finfo);

        return strtolower($mime);
    }

    private function slug(string $s): string
    {
        $clean = (string) preg_replace('/[^a-z0-9]+/i', '-', $s);
        return trim(strtolower($clean), '-') ?: 'img';
    }
}
