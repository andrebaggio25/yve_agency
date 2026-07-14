<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    private int    $status  = 200;
    private array  $headers = [];
    private string $body    = '';

    // -------------------------------------------------------------------------
    // Factory methods
    // -------------------------------------------------------------------------

    public static function view(string $template, array $data = [], int $status = 200): static
    {
        $instance         = new static();
        $instance->status = $status;
        $instance->body   = View::render($template, $data);
        $instance->headers['Content-Type'] = 'text/html; charset=UTF-8';
        return $instance;
    }

    public static function json(mixed $data, int $status = 200): static
    {
        $instance         = new static();
        $instance->status = $status;
        $instance->body   = (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $instance->headers['Content-Type'] = 'application/json; charset=UTF-8';
        return $instance;
    }

    public static function redirect(string $url, int $status = 302): static
    {
        $instance         = new static();
        $instance->status = $status;
        $instance->headers['Location'] = $url;
        return $instance;
    }

    public static function text(string $body, int $status = 200): static
    {
        $instance         = new static();
        $instance->status = $status;
        $instance->body   = $body;
        $instance->headers['Content-Type'] = 'text/plain; charset=UTF-8';
        return $instance;
    }

    public static function notFound(string $message = 'Not Found'): static
    {
        return static::view('errors.404', ['message' => $message], 404);
    }

    public static function forbidden(string $message = 'Forbidden'): static
    {
        return static::view('errors.403', ['message' => $message], 403);
    }

    // -------------------------------------------------------------------------
    // Builder methods (immutable-style)
    // -------------------------------------------------------------------------

    public function withStatus(int $status): static
    {
        $clone         = clone $this;
        $clone->status = $status;
        return $clone;
    }

    public function withHeader(string $name, string $value): static
    {
        $clone                  = clone $this;
        $clone->headers[$name]  = $value;
        return $clone;
    }

    public function withBody(string $body): static
    {
        $clone       = clone $this;
        $clone->body = $body;
        return $clone;
    }

    // -------------------------------------------------------------------------
    // Sending
    // -------------------------------------------------------------------------

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        // Security headers (always sent)
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Content-Security-Policy: ' . self::contentSecurityPolicy());

        // HSTS só faz sentido (e só é honrado) sob HTTPS.
        if (self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        echo $this->body;
    }

    /**
     * CSP do app (SEC-03 + SEC-10).
     *
     * Depois do FE-01 (self-host), **nenhum script vem de CDN** — a allowlist
     * de origens é só `'self'`. Ganho real: um CDN comprometido não executa
     * mais nada aqui.
     *
     * `'unsafe-eval'` é **obrigatório**, não descuido: o Alpine.js avalia as
     * expressões dos atributos (`x-data`, `@click`, `x-text`) com
     * `new AsyncFunction()`. Sem ele, o Alpine morre e a interface inteira
     * (menus, modais, upload) para de funcionar — verificado no navegador.
     * Removê-lo exige migrar para o build `@alpinejs/csp`, que proíbe
     * expressão inline e obrigaria a reescrever todas as views (ver SEC-10 no
     * PLANO_MESTRE). `'unsafe-inline'` idem, enquanto houver `<script>` inline.
     *
     * Outras exceções conscientes:
     * - `style-src` inline: Tailwind/Alpine escrevem estilo inline em runtime.
     * - `img-src https:`: logo/thumbnail de agência e do Drive vêm de fora.
     * - `connect-src googleapis`: PUTs do upload direto pro Drive (UP-01).
     * - `frame-src` Drive/Docs/YouTube: preview de arquivo e vídeo embutido.
     */
    private static function contentSecurityPolicy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' data: https:",
            "media-src 'self' https://drive.google.com blob:",
            "frame-src https://drive.google.com https://docs.google.com https://www.youtube.com https://www.youtube-nocookie.com",
            "connect-src 'self' https://www.googleapis.com",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) == 443)
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }

    public function getStatus(): int    { return $this->status; }
    public function getBody(): string   { return $this->body; }
    public function getHeaders(): array { return $this->headers; }
}
