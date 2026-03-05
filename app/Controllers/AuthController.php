<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Session;
use RuntimeException;

final class AuthController extends Controller
{
    public function showLogin(Request $request): void
    {
        $this->view('auth/login', [
            'title' => 'Entrar',
        ]);
    }

    public function login(Request $request): void
    {
        $email = mb_strtolower((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');

        Session::flashInput(['email' => $email]);

        if ($email === '' || $password === '') {
            flash('error', 'Informe e-mail e senha.');
            $this->redirect('/login');
        }

        try {
            $success = $this->app->auth()->attempt($email, $password, $request->ip(), $request->userAgent());
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
            $this->redirect('/login');
        }

        if (!$success) {
            flash('error', 'Credenciais inválidas.');
            $this->redirect('/login');
        }

        if ($this->app->auth()->passwordExpired()) {
            flash('error', 'Senha expirada. Atualize sua senha para continuar.');
            $this->redirect('/users/password');
        }

        flash('success', 'Login realizado com sucesso.');
        $this->redirect('/dashboard');
    }

    public function logout(Request $request): void
    {
        $this->app->auth()->logout($request->ip(), $request->userAgent());

        flash('success', 'Sessão encerrada.');
        $this->redirect('/login');
    }
}
