<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use App\Support\Auth;
use App\Support\ActivityLogger;
use App\Support\LoginThrottle;
use App\Services\EmailService;

class AuthService
{
    /**
     * Hash Argon2id de uma senha aleatória descartada. Conferido quando o e-mail não
     * existe, para a resposta levar o mesmo tempo e não revelar quais contas são reais.
     */
    private const DUMMY_HASH = '$argon2id$v=19$m=65536,t=4,p=1$c0Z0WUpFbXFxcmxxejFoYw$Gg6dEe31FXvGJdiJQy1/JjHmeML2KYMZ/9eK804mmMI';

    public function __construct(
        private readonly UserRepository $userRepo,
        private readonly EmailService   $email,
    ) {}

    public function attempt(string $email, string $password): array
    {
        if (empty($email) || empty($password)) {
            return ['success' => false, 'message' => t('auth.fill_credentials')];
        }

        $locked = LoginThrottle::secondsLocked($email);
        if ($locked > 0) {
            return ['success' => false, 'message' => t('auth.account_locked', ['minutes' => (string) (int) ceil($locked / 60)])];
        }

        $user  = $this->userRepo->findByEmail($email);
        $valid = password_verify($password, $user['password_hash'] ?? self::DUMMY_HASH);

        if (!$user || !$valid) {
            LoginThrottle::recordFailure($email);
            if ($user) {
                ActivityLogger::log('login_failed', 'auth', null, null, ['email' => $email]);
            }
            return ['success' => false, 'message' => t('auth.invalid_credentials')];
        }

        // Só depois de conferir a senha: quem não tem a senha não descobre o status da conta.
        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => t('auth.inactive_user')];
        }

        LoginThrottle::clear($email);

        // Platform admin tem acesso total — sem permissions/clients de agência
        if (!empty($user['is_platform_admin'])) {
            Auth::login($user, [], []);
            $_SESSION['locale'] = \App\Core\Lang::normalize($user['agency_language'] ?? $user['language'] ?? 'pt');
            $this->userRepo->updateLastLogin($user['id']);
            ActivityLogger::log('login', 'auth', $user['id'], null, ['ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
            return ['success' => true, 'redirect' => '/admin'];
        }

        $permissions = $this->userRepo->loadPermissions($user['id']);
        $clientIds   = $this->userRepo->loadClientIds($user['id']);

        Auth::login($user, $permissions, $clientIds);

        // Store user's language preference in session for locale loading
        $_SESSION['locale'] = \App\Core\Lang::normalize($user['agency_language'] ?? $user['language'] ?? 'pt');

        $this->userRepo->updateLastLogin($user['id']);

        ActivityLogger::log('login', 'auth', $user['id'], null, [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        ]);

        return ['success' => true, 'redirect' => null];
    }

    public function logout(): void
    {
        ActivityLogger::log('logout', 'auth', Auth::id());
        Auth::logout();
    }

    public function sendPasswordResetLink(string $email): array
    {
        $user = $this->userRepo->findByEmail($email);

        if (!$user) {
            // Don't reveal whether the email exists
            return ['success' => true];
        }

        $token = bin2hex(random_bytes(32));

        $this->userRepo->savePasswordResetToken((int) $user['id'], $token);

        $appUrl  = rtrim(env('APP_URL', 'http://localhost'), '/');
        $resetUrl = "{$appUrl}/redefinir-senha/{$token}";
        $appName  = env('APP_NAME', 'YVE Agency');

        $this->email->send($user['email'], $user['name'], 'password_reset', [
            'user_name' => $user['name'],
            'reset_url' => $resetUrl,
            'app_name'  => $appName,
        ]);

        return ['success' => true];
    }

    public function isResetTokenValid(string $token): bool
    {
        return $this->userRepo->findValidResetToken($token) !== null;
    }

    public function resetPassword(string $token, string $password): array
    {
        if (strlen($password) < 8) {
            return ['success' => false, 'message' => t('auth.password_too_short')];
        }

        $resetRecord = $this->userRepo->findValidResetToken($token);

        if (!$resetRecord) {
            return ['success' => false, 'message' => t('auth.reset_link_invalid')];
        }

        $hash = password_hash($password, PASSWORD_ARGON2ID);
        // Trocar o hash derruba as sessões abertas: o AuthMiddleware compara a impressão digital.
        $this->userRepo->updatePassword($resetRecord['user_id'], $hash);
        $this->userRepo->deleteResetToken($token);

        LoginThrottle::clear((string) $resetRecord['email']);

        ActivityLogger::log('password_reset', 'auth', $resetRecord['user_id']);

        return ['success' => true];
    }
}
