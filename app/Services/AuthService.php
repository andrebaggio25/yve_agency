<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use App\Support\Auth;
use App\Support\ActivityLogger;

class AuthService
{
    public function __construct(private readonly UserRepository $userRepo) {}

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

        $permissions = $this->userRepo->loadPermissions($user['id']);
        $clientIds   = $this->userRepo->loadClientIds($user['id']);

        Auth::login($user, $permissions, $clientIds);

        $this->userRepo->updateLastLogin($user['id']);

        ActivityLogger::log('login', 'auth', $user['id'], null, [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        ]);

        return ['success' => true];
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

        // TODO Phase 3: send email via EmailService
        // For now: log the token (dev only)
        if (env('APP_ENV') === 'development') {
            logger("Password reset token for {$email}: {$token}");
        }

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
