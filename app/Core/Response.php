<?php

declare(strict_types=1);

namespace App\Core;

class Response
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
     * CSP alinhada ao que o app carrega hoje: Tailwind/Alpine/Chart/marked por
     * CDN (exigem 'unsafe-inline'/'unsafe-eval'), embeds do Drive/Docs/YouTube e
     * fontes do Google. Ainda assim restringe origens de script, bloqueia
     * object/embed, trava base-uri e frame-ancestors (anti-clickjacking).
     * Endurecer para nonce/sem unsafe depois do self-host de assets (PERF-01).
     */
    private static function contentSecurityPolicy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://www.youtube.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net",
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' data: https:",
            "media-src 'self' https://drive.google.com blob:",
            "frame-src https://drive.google.com https://docs.google.com https://www.youtube.com https://www.youtube-nocookie.com",
            "connect-src 'self'",
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
