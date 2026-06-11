<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use App\Support\Auth;
use App\Support\ActivityLogger;
use App\Services\EmailService;

class AuthService
{
    public function __construct(
        private readonly UserRepository $userRepo,
        private readonly EmailService   $email,
    ) {}

    public function attempt(string $email, string $password, bool $remember = false): array
    {
        if (empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'Preencha e-mail e senha.'];
        }

        $user = $this->userRepo->findByEmail($email);

        if (!$user) {
            return ['success' => false, 'message' => 'Credenciais inválidas.'];
        }

        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'Usuário inativo. Contate o administrador.'];
        }

        if (!password_verify($password, $user['password_hash'])) {
            ActivityLogger::log('login_failed', 'auth', null, null, ['email' => $email]);
            return ['success' => false, 'message' => 'Credenciais inválidas.'];
        }

        // Platform admin tem acesso total — sem permissions/clients de agência
        if (!empty($user['is_platform_admin'])) {
            Auth::login($user, [], []);
            $_SESSION['locale'] = \App\Core\Lang::normalize($user['language'] ?? 'pt');
            $this->userRepo->updateLastLogin($user['id']);
            ActivityLogger::log('login', 'auth', $user['id'], null, ['ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
            return ['success' => true, 'redirect' => '/admin'];
        }

        $permissions = $this->userRepo->loadPermissions($user['id']);
        $clientIds   = $this->userRepo->loadClientIds($user['id']);

        Auth::login($user, $permissions, $clientIds);

        // Store user's language preference in session for locale loading
        $_SESSION['locale'] = \App\Core\Lang::normalize($user['language'] ?? 'pt');

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

        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $this->userRepo->savePasswordResetToken($user['id'], $token, $expiresAt);

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

    public function resetPassword(string $token, string $password): array
    {
        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'A senha deve ter pelo menos 8 caracteres.'];
        }

        $resetRecord = $this->userRepo->findValidResetToken($token);

        if (!$resetRecord) {
            return ['success' => false, 'message' => 'Token inválido ou expirado.'];
        }

        $hash = password_hash($password, PASSWORD_ARGON2ID);
        $this->userRepo->updatePassword($resetRecord['user_id'], $hash);
        $this->userRepo->deleteResetToken($token);

        ActivityLogger::log('password_reset', 'auth', $resetRecord['user_id']);

        return ['success' => true];
    }
}
