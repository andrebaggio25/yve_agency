<?php

declare(strict_types=1);

// API routes (v1)
// Autenticação via token Bearer (JWT) — fase futura.
// Por ora, apenas um endpoint de health-check.

$router->get('/api/health', [\App\Controllers\Api\HealthController::class, 'index']);
