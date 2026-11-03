<?php

declare(strict_types=1);

namespace Uchet\Middleware;

use Uchet\Core\Csrf;
use Uchet\Core\Request;

/**
 * Проверка CSRF-токена для всех небезопасных методов (POST/PUT/PATCH/DELETE).
 */
final class CsrfMiddleware implements Middleware
{
    public function handle(): void
    {
        $method = Request::method();
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            Csrf::check();
        }
    }
}
