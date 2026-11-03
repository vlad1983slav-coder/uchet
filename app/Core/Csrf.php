<?php

declare(strict_types=1);

namespace Uchet\Core;

/**
 * CSRF-токен на сессии. Все POST/PUT/PATCH/DELETE проходят через CsrfMiddleware.
 * Токен передаётся полем формы `_csrf` или заголовком X-CSRF-Token (для AJAX).
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::SESSION_KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    /** HTML-скрытое поле для вставки в форму: <?= csrf_field() ?> */
    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function validate(?string $token = null): bool
    {
        $token ??= Request::post('_csrf') ?? Request::header('X-CSRF-Token');

        return is_string($token)
            && $token !== ''
            && hash_equals(self::token(), $token);
    }

    /** @throws CsrfException */
    public static function check(): void
    {
        if (!self::validate()) {
            throw new CsrfException();
        }
    }
}
