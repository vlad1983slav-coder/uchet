<?php

declare(strict_types=1);

namespace Uchet\Middleware;

use Uchet\Core\Auth;
use Uchet\Core\HttpException;
use Uchet\Core\Request;
use Uchet\Core\Response;
use Uchet\Core\Session;

/**
 * Требует авторизации. Гость → редирект на /login (или 401 JSON для AJAX).
 */
final class AuthMiddleware implements Middleware
{
    public function handle(): void
    {
        if (Auth::check()) {
            return;
        }
        if (Request::wantsJson()) {
            throw new HttpException(401, 'Требуется авторизация');
        }
        Session::flash('info', 'Пожалуйста, войдите в систему');
        Response::redirect('/login');
    }
}
