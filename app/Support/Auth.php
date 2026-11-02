<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;
use App\Core\HttpException;
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
    /**
     * Cache por requisição de client_id → agency_id (SEC-11). A fronteira de
     * tenant do acesso a cliente é uma consulta; sem cache, uma tela que chama
     * `canAccessClient` por linha viraria N+1.
     *
     * @var array<int,int|null>
     */
    private static array $clientAgency = [];

    // -------------------------------------------------------------------------
    // Login / logout
    // -------------------------------------------------------------------------

    public static function login(array $user, array $permissions, array $clientIds): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        self::forgetClientAgencies();

        // O hash nunca fica no arquivo de sessão; basta a impressão digital dele.
        $_SESSION['auth_fingerprint']   = self::passwordFingerprint((string) ($user['password_hash'] ?? ''));
        unset($user['password_hash']);

        $_SESSION['user']               = $user;
        $_SESSION['permissions']        = $permissions;
        $_SESSION['client_ids']         = $clientIds;
        $_SESSION['is_platform_admin']  = !empty($user['is_platform_admin']);
    }

    public static function passwordFingerprint(string $passwordHash): string
    {
        return hash('sha256', $passwordHash);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        self::forgetClientAgencies();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

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
        if (!$user) return null;
        return $user['agency_id'] !== null ? (int) $user['agency_id'] : null;
    }

    public static function isPlatformAdmin(): bool
    {
        return (bool) ($_SESSION['is_platform_admin'] ?? false);
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

    /**
     * Ninguém concede o que não tem (SEC-12): sem isto, quem edita perfis ou usuários
     * se promove a admin. Admin da plataforma passa, como no resto do RBAC.
     *
     * @param list<string> $permissions
     */
    public static function canGrant(array $permissions): bool
    {
        if (self::isPlatformAdmin()) {
            return true;
        }

        return array_diff($permissions, $_SESSION['permissions'] ?? []) === [];
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

    /**
     * O usuário alcança este cliente?
     *
     * São duas perguntas, e a primeira é a fronteira de tenant (SEC-11):
     * `clients.view_all` vale só DENTRO da agência do usuário — antes ela
     * liberava cliente de qualquer agência, porque nada aqui olhava o dono do
     * cliente. Só depois disso vale a pergunta de alcance: a agência toda
     * (view_all) ou os clientes com acesso explícito.
     */
    public static function canAccessClient(int $clientId): bool
    {
        // Admin da plataforma passa, como no resto do RBAC.
        if (self::isPlatformAdmin()) {
            return true;
        }

        $agencyId = self::agencyId();
        if ($agencyId === null || self::clientAgencyId($clientId) !== $agencyId) {
            return false;
        }

        return self::can('clients.view_all')
            || in_array($clientId, $_SESSION['client_ids'] ?? [], true);
    }

    /**
     * Clientes que o usuário alcança, para **recortar listagem** (SEC-11).
     * `null` = a agência inteira (`clients.view_all` ou admin da plataforma).
     *
     * Mesma régua de `canAccessClient`, e de propósito: lista que mostra o que
     * o detalhe nega é permissão só na tela. Sai da sessão (o AuthMiddleware a
     * revalida), então não custa consulta.
     *
     * @return list<int>|null
     */
    public static function reachableClientIds(): ?array
    {
        if (self::isPlatformAdmin() || self::can('clients.view_all')) {
            return null;
        }

        return array_values(array_map('intval', $_SESSION['client_ids'] ?? []));
    }

    /**
     * Pré-carrega o mapa cliente → agência com linhas que a tela já tem em mão
     * (precisa de `id` e `agency_id`), para a listagem não pagar uma consulta
     * por linha ao chamar `canAccessClient`.
     *
     * @param iterable<array<string,mixed>> $clients
     */
    public static function primeClientAgencies(iterable $clients): void
    {
        foreach ($clients as $client) {
            if (!isset($client['id'], $client['agency_id'])) continue;
            self::$clientAgency[(int) $client['id']] = (int) $client['agency_id'];
        }
    }

    /** Descarta o cache do mapa cliente → agência (login, logout e testes). */
    public static function forgetClientAgencies(): void
    {
        self::$clientAgency = [];
    }

    /** Agência dona do cliente, ou null se o cliente não existe. */
    private static function clientAgencyId(int $clientId): ?int
    {
        if (array_key_exists($clientId, self::$clientAgency)) {
            return self::$clientAgency[$clientId];
        }

        try {
            $stmt = Database::connection()->prepare('SELECT agency_id FROM clients WHERE id = :id');
            $stmt->execute([':id' => $clientId]);
            $value = $stmt->fetchColumn();
        } catch (\Throwable $e) {
            // Falha de banco não pode virar permissão: nega e deixa rastro no log.
            error_log('Auth::clientAgencyId falhou: ' . $e->getMessage());
            $value = false;
        }

        return self::$clientAgency[$clientId] = ($value === false || $value === null) ? null : (int) $value;
    }

    // -------------------------------------------------------------------------
    // Guards — abortam a requisição se a condição não for satisfeita
    // -------------------------------------------------------------------------

    // Os guards LANÇAM HttpException (o Router converte em Response). Antes
    // faziam `send(); exit;` — o que matava o processo e tornava a autorização
    // impossível de testar por HTTP. Comportamento para o usuário é idêntico.

    public static function requireLogin(): void
    {
        if (!self::check()) {
            throw HttpException::unauthenticated(self::isAjax());
        }
    }

    public static function requirePermission(string $permission): void
    {
        self::requireLogin();

        // Platform admin bypasses all tenant RBAC
        if (self::isPlatformAdmin()) return;

        if (!self::can($permission)) {
            throw HttpException::forbidden(self::isAjax(), ['permission' => $permission]);
        }
    }

    /**
     * Exige **pelo menos uma** das permissões. Para tela que serve a papéis
     * diferentes com permissões diferentes (ex.: o Relatório Executivo, que o
     * financeiro alcança por `clients.view_basic` e a equipe por `clients.view`).
     */
    public static function requireAnyPermission(string ...$permissions): void
    {
        self::requireLogin();

        if (self::isPlatformAdmin()) return;

        if (!self::canAny(...$permissions)) {
            throw HttpException::forbidden(self::isAjax(), ['permission' => implode(' | ', $permissions)]);
        }
    }

    public static function requirePlatformAdmin(): void
    {
        self::requireLogin();

        if (!self::isPlatformAdmin()) {
            throw HttpException::forbidden(self::isAjax());
        }
    }

    public static function requireClientAccess(int $clientId): void
    {
        self::requireLogin();

        if (!self::canAccessClient($clientId)) {
            throw HttpException::forbidden(self::isAjax());
        }
    }

    // -------------------------------------------------------------------------
    // Private
    // -------------------------------------------------------------------------

    public static function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }
}
