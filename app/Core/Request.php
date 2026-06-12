<?php

declare(strict_types=1);

namespace App\Core;

class Request
{
    private array $params = [];

    private function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly array  $query,
        private readonly array  $body,
        private readonly array  $files,
        private readonly array  $server,
        private readonly array  $cookies,
    ) {}

    public static function fromGlobals(): static
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Method override via _method in POST body
        if ($method === 'POST') {
            $override = strtoupper($_POST['_method'] ?? '');
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        $body = $_POST;
        if (empty($body) && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            $body = json_decode(self::rawInput() ?: '{}', true) ?? [];
        }

        return new static(
            method:  $method,
            uri:     $uri,
            query:   $_GET,
            body:    $body,
            files:   $_FILES,
            server:  $_SERVER,
            cookies: $_COOKIE,
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->uri;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function isGet(): bool  { return $this->method === 'GET'; }
    public function isPost(): bool { return $this->method === 'POST'; }
    public function isPut(): bool  { return $this->method === 'PUT'; }
    public function isDelete(): bool { return $this->method === 'DELETE'; }

    /** Get from POST body, GET query, or route params */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $this->params[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function file(string $key): mixed
    {
        return $this->files[$key] ?? null;
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    /** Returns only specified keys from the body. Accepts array or variadic strings. */
    public function only(array|string ...$keys): array
    {
        $flat = (count($keys) === 1 && is_array($keys[0])) ? $keys[0] : $keys;
        return array_intersect_key(array_merge($this->query, $this->body), array_flip($flat));
    }

    /** Parse JSON request body and return value at key (or full decoded array if no key) */
    public function json(string $key = '', mixed $default = null): mixed
    {
        static $decoded = null;
        if ($decoded === null) {
            $decoded = json_decode(self::rawInput() ?: '{}', true) ?? [];
        }
        if ($key === '') return $decoded;
        return $decoded[$key] ?? $default;
    }

    /** Raw php://input, cached so it can be read more than once. */
    public static function rawInput(): string
    {
        static $cache = null;
        if ($cache === null) {
            $cache = file_get_contents('php://input') ?: '';
        }
        return $cache;
    }

    /** Returns all body + query input */
    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function ip(): string
    {
        return $this->server['HTTP_X_FORWARDED_FOR']
            ?? $this->server['REMOTE_ADDR']
            ?? '0.0.0.0';
    }

    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    public function isAjax(): bool
    {
        return ($this->server['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    public function wantsJson(): bool
    {
        return $this->isAjax() || str_contains($this->server['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function bearerToken(): ?string
    {
        $header = $this->server['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        return null;
    }
}
