<?php

declare(strict_types=1);

use Uchet\Core\Auth;
use Uchet\Core\Csrf;
use Uchet\Core\HttpException;
use Uchet\Core\Response;
use Uchet\Core\Router;
use Uchet\Core\Session;

if (!defined('ROOT')) {
    define('ROOT', dirname(__DIR__, 2));
}

if (!function_exists('env')) {
    /** Переменная окружения с значением по умолчанию. */
    function env(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);

        return $value === false ? $default : $value;
    }
}

if (!function_exists('config')) {
    /** Доступ к конфигу по ключу через точку: config('db.host'). */
    function config(string $key, mixed $default = null): mixed
    {
        static $config = null;
        if ($config === null) {
            $file = ROOT . '/config/config.php';
            $config = is_file($file) ? require $file : [];
        }
        $value = $config;
        foreach (explode('.', $key) as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }

        return $value;
    }
}

if (!function_exists('e')) {
    /** Экранирование вывода для HTML. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    /** URL именованного маршрута: url('receipts.edit', ['id' => 5]). */
    function url(string $name, array $params = []): string
    {
        return Router::url($name, $params);
    }
}

if (!function_exists('asset')) {
    /** URL статического ресурса из public/. */
    function asset(string $path): string
    {
        $base = rtrim((string) config('app.base_url', ''), '/');

        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $code = 302): never
    {
        Response::redirect($url, $code);
    }
}

if (!function_exists('abort')) {
    /** Прервать запрос с HTTP-ошибкой. */
    function abort(int $code, string $message = ''): never
    {
        throw new HttpException($code, $message);
    }
}

if (!function_exists('money')) {
    /** Сумма DECIMAL(15,2) → "1 234 567.89 ₽". */
    function money(mixed $value, ?string $currency = null): string
    {
        $currency ??= (string) config('app.currency', 'RUB');
        $formatted = number_format((float) $value, 2, '.', ' ');

        return match ($currency) {
            'RUB' => $formatted . ' ₽',
            'USD' => '$' . $formatted,
            'EUR' => $formatted . ' €',
            default => $formatted . ' ' . $currency,
        };
    }
}

if (!function_exists('qty')) {
    /** Количество DECIMAL(15,3) → "1 234.500". */
    function qty(mixed $value): string
    {
        return number_format((float) $value, 3, '.', ' ');
    }
}

if (!function_exists('dt')) {
    /** Дата/время из БД → строка в формате 1С (d.m.Y H:i). */
    function dt(mixed $value, string $format = 'd.m.Y H:i'): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $ts = is_numeric($value) ? (int) $value : strtotime((string) $value);

        return $ts === false ? (string) $value : date($format, $ts);
    }
}

if (!function_exists('d')) {
    /** Только дата: d.m.Y. */
    function d(mixed $value): string
    {
        return dt($value, 'd.m.Y');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    /** Скрытое поле с CSRF-токеном для форм. */
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('can')) {
    /** Проверка права текущего пользователя: can('documents.post'). */
    function can(string $permission): bool
    {
        return Auth::can($permission);
    }
}

if (!function_exists('user')) {
    /** Текущий пользователь (массив из БД) или null. */
    function user(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('old')) {
    /** Старое значение поля формы после редиректа. */
    function old(string $key, mixed $default = null): mixed
    {
        return Session::getOld($key, $default);
    }
}

if (!function_exists('errors')) {
    /** Flash-ошибки валидации: ['поле' => ['сообщение', ...]]. */
    function errors(): array
    {
        $errors = Session::getFlash('errors', []);

        return is_array($errors) ? $errors : [];
    }
}

if (!function_exists('error_for')) {
    /** Первая ошибка конкретного поля или null. */
    function error_for(string $field): ?string
    {
        $errors = errors();

        return $errors[$field][0] ?? null;
    }
}

if (!function_exists('flash_message')) {
    /** Flash-сообщение типа success/error/info/warning или null. */
    function flash_message(string $type): ?string
    {
        $message = Session::getFlash($type);

        return is_string($message) ? $message : null;
    }
}
