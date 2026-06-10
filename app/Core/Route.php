<?php

declare(strict_types=1);

namespace App\Core;

class Route
{
    public function __construct(
        public readonly string $method,
        public readonly string $uri,
        public readonly string $pattern,
        public readonly array  $handler,
        public readonly array  $middlewares = [],
        public readonly ?string $name = null,
    ) {}
}
