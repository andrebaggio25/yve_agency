<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function showLogin(Request $request): Response
    {
        if (\App\Support\Auth::check()) {
            return $this->redirect('/dashboard');
        }

        return $this->view('auth.login');
    }

    public function login(Request $request): Response
    {
        $email    = trim((string) $request->post('email', ''));
        $password = (string) $request->post('password', '');

        $result = $this->authService->attempt($email, $password);

        if (!$result['success']) {
            $this->withError($result['message'])->withInput(['email' => $email]);
            return $this->redirect('/login');
        }

        // Platform admin redirects to /admin panel, not dashboard
        if (!empty($result['redirect'])) {
            return $this->redirect($result['redirect']);
        }

        $redirect = flash('redirect_after_login') ?? '/dashboard';
        return $this->redirect((string) $redirect);
    }

    public function logout(Request $request): Response
    {
        $this->authService->logout();
        return $this->redirect('/login');
    }

    public function showForgotPassword(Request $request): Response
    {
        return $this->view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): Response
    {
        $email = trim((string) $request->post('email', ''));
        $this->authService->sendPasswordResetLink($email);

        // Mesma mensagem para e-mail existente ou não: a tela não confirma contas.
        $this->withSuccess(t('auth.reset_link_sent'));
        return $this->redirect('/esqueci-senha');
    }

    public function showResetPassword(Request $request): Response
    {
        $token = (string) $request->param('token');

        return $this->view('auth.reset-password', [
            'token' => $token,
            'valid' => $this->authService->isResetTokenValid($token),
        ]);
    }

    public function resetPassword(Request $request): Response
    {
        $token    = (string) $request->post('token', '');
        $password = (string) $request->post('password', '');
        $confirm  = (string) $request->post('password_confirmation', '');

        // O token volta na URL do redirect: só hexadecimal, para não virar injeção de caminho.
        if (!ctype_xdigit($token)) {
            $this->withError(t('auth.reset_link_invalid'));
            return $this->redirect('/esqueci-senha');
        }

        if ($password !== $confirm) {
            $this->withError(t('auth.passwords_mismatch'));
            return $this->redirect('/redefinir-senha/' . $token);
        }

        $result = $this->authService->resetPassword($token, $password);

        if (!$result['success']) {
            $this->withError($result['message']);
            return $this->redirect('/redefinir-senha/' . $token);
        }

        $this->withSuccess(t('auth.password_reset_done'));
        return $this->redirect('/login');
    }
}
