<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Response;

/**
 * Ponto único de verificação de autenticação e autorização (RBAC).
 *
 * Carrega em sessão no login:
 *   $_SESSION['user']        → dados do usuário
 *   $_SESSION['permissions'] → array de slugs ('content.view', 'clients.edit', ...)
 *   $_SESSION['client_ids']  → IDs dos clientes que o usuário pode acessar
 */
class Auth
{
    // -------------------------------------------------------------------------
    // Login / logout
    // -------------------------------------------------------------------------

    public static function login(array $user, array $permissions, array $clientIds): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION['user']        = $user;
        $_SESSION['permissions'] = $permissions;
        $_SESSION['client_ids']  = $clientIds;
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly'],
            );
        }

        session_destroy();
    }

    // -------------------------------------------------------------------------
    // User
    // -------------------------------------------------------------------------

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int) $user['id'] : null;
    }

    public static function agencyId(): ?int
    {
        $user = self::user();
        return $user ? (int) $user['agency_id'] : null;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    public static function guest(): bool
    {
        return !self::check();
    }

    // -------------------------------------------------------------------------
    // Permissions
    // -------------------------------------------------------------------------

    public static function can(string $permission): bool
    {
        return in_array($permission, $_SESSION['permissions'] ?? [], true);
    }

    public static function cannot(string $permission): bool
    {
        return !self::can($permission);
    }

    public static function canAny(string ...$permissions): bool
    {
        foreach ($permissions as $p) {
            if (self::can($p)) return true;
        }
        return false;
    }

    public static function canAll(string ...$permissions): bool
    {
        foreach ($permissions as $p) {
            if (!self::can($p)) return false;
        }
        return true;
    }

    // -------------------------------------------------------------------------
    // Client access
    // -------------------------------------------------------------------------

    public static function canAccessClient(int $clientId): bool
    {
        if (self::can('clients.view_all')) {
            return true;
        }

        return in_array($clientId, $_SESSION['client_ids'] ?? [], true);
    }

    // -------------------------------------------------------------------------
    // Guards — abortam a requisição se a condição não for satisfeita
    // -------------------------------------------------------------------------

    public static function requireLogin(): void
    {
        if (!self::check()) {
            if (self::isAjax()) {
                Response::json(['error' => 'Unauthenticated'], 401)->send();
                exit;
            }
            Response::redirect('/login')->send();
            exit;
        }
    }

    public static function requirePermission(string $permission): void
    {
        self::requireLogin();

        if (!self::can($permission)) {
            if (self::isAjax()) {
                Response::json(['error' => 'Forbidden'], 403)->send();
                exit;
            }
            Response::view('errors.403', ['permission' => $permission], 403)->send();
            exit;
        }
    }

    public static function requireClientAccess(int $clientId): void
    {
        self::requireLogin();

        if (!self::canAccessClient($clientId)) {
            if (self::isAjax()) {
                Response::json(['error' => 'Client access denied'], 403)->send();
                exit;
            }
            Response::view('errors.403', [], 403)->send();
            exit;
        }
    }

    // -------------------------------------------------------------------------
    // Private
    // -------------------------------------------------------------------------

    private static function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }
}
