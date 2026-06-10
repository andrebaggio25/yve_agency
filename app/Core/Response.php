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

        echo $this->body;
    }

    public function getStatus(): int    { return $this->status; }
    public function getBody(): string   { return $this->body; }
    public function getHeaders(): array { return $this->headers; }
}
