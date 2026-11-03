<?php

declare(strict_types=1);

namespace Uchet\Middleware;

use Uchet\Core\Auth;
use Uchet\Core\Response;

/**
 * Только для гостей (страница входа). Авторизованного отправляет на главную.
 */
final class GuestMiddleware implements Middleware
{
    public function handle(): void
    {
        if (!Auth::check()) {
            return;
        }
        Response::redirect('/');
    }
}
