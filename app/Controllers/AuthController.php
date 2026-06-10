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
        $remember = (bool)   $request->post('remember', false);

        $result = $this->authService->attempt($email, $password, $remember);

        if (!$result['success']) {
            $this->withError($result['message'])->withInput(['email' => $email]);
            return $this->redirect('/login');
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
        $email  = trim((string) $request->post('email', ''));
        $result = $this->authService->sendPasswordResetLink($email);

        $this->withSuccess('Se o e-mail existir, você receberá um link em breve.');
        return $this->redirect('/esqueci-senha');
    }

    public function showResetPassword(Request $request): Response
    {
        $token = $request->param('token');
        return $this->view('auth.reset-password', ['token' => $token]);
    }

    public function resetPassword(Request $request): Response
    {
        $token    = (string) $request->post('token', '');
        $password = (string) $request->post('password', '');
        $confirm  = (string) $request->post('password_confirmation', '');

        if ($password !== $confirm) {
            $this->withError('As senhas não coincidem.');
            return $this->redirect('/redefinir-senha/' . $token);
        }

        $result = $this->authService->resetPassword($token, $password);

        if (!$result['success']) {
            $this->withError($result['message']);
            return $this->redirect('/redefinir-senha/' . $token);
        }

        $this->withSuccess('Senha redefinida com sucesso. Faça login.');
        return $this->redirect('/login');
    }
}
