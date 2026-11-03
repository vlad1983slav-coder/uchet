<?php

declare(strict_types=1);

namespace Uchet\Controllers;

use Uchet\Core\Auth;
use Uchet\Core\Controller;
use Uchet\Core\Session;

/**
 * Вход/выход. Полные контроллеры справочников и документов — в п. 5 ТЗ.
 */
final class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirect('/');
        }
        $this->render('auth.login', ['title' => 'Вход в систему'], null);
    }

    public function login(): void
    {
        $this->csrf();
        $data = $this->validate([
            'login' => 'required|string|max:64',
            'password' => 'required|string|min:6',
        ]);

        if (!Auth::attempt($data['login'], $data['password'])) {
            Session::flash('errors', ['login' => ['Неверный логин или пароль']]);
            Session::flashInput(['login' => $data['login']]);
            $this->redirect('/login');
        }

        Session::flash('success', 'Добро пожаловать, ' . (string) (Auth::user()['full_name'] ?? ''));
        $this->redirect('/');
    }

    public function logout(): void
    {
        Auth::logout();
        Session::flash('info', 'Вы вышли из системы');
        $this->redirect('/login');
    }
}
