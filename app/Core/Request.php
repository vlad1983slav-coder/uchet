<?php

declare(strict_types=1);

namespace Uchet\Core;

/**
 * Обёртка над суперглобалами запроса (статический доступ, без DI-церемонии).
 */
final class Request
{
    private static ?array $jsonCache = null;

    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    public static function isMethod(string $method): bool
    {
        return self::method() === strtoupper($method);
    }

    public static function isAjax(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    public static function wantsJson(): bool
    {
        return self::isAjax()
            || str_contains((string) self::header('Accept'), 'application/json')
            || str_contains((string) self::header('Content-Type'), 'application/json');
    }

    /** Путь запроса без query string и без базового подкаталога установки. */
    public static function path(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');

        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if (str_contains($script, 'index.php')) {
            $base = rtrim((string) dirname($script), '/');
            if ($base !== '' && str_starts_with($path, $base)) {
                $path = substr($path, strlen($base));
            }
        }

        $path = '/' . trim($path, '/');

        return $path === '' ? '/' : $path;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public static function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    /** Все параметры (POST перекрывает GET). */
    public static function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    /** Распарсенный JSON-body (для AJAX-эндпоинтов). */
    public static function json(): array
    {
        if (self::$jsonCache === null) {
            $raw = (string) file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            self::$jsonCache = is_array($decoded) ? $decoded : [];
        }

        return self::$jsonCache;
    }

    /** Только перечисленные ключи. */
    public static function only(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $_POST) || array_key_exists($key, $_GET)) {
                $result[$key] = self::input((string) $key);
            }
        }

        return $result;
    }

    public static function filled(string $key): bool
    {
        $value = self::input($key);

        return $value !== null && $value !== '';
    }

    public static function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return isset($_SERVER[$key]) ? (string) $_SERVER[$key] : null;
    }

    public static function ip(): string
    {
        $forwarded = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($forwarded !== '') {
            return trim(explode(',', $forwarded)[0]);
        }

        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    public static function userAgent(): string
    {
        return (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    }

    public static function referer(): string
    {
        return (string) ($_SERVER['HTTP_REFERER'] ?? '');
    }

    // --- параметры списков (пагинация, сортировка, поиск) ---

    public static function page(): int
    {
        return max(1, (int) self::get('page', 1));
    }

    public static function perPage(int $default = 20, int $max = 100): int
    {
        return min($max, max(1, (int) self::get('per_page', $default)));
    }

    /** Колонка сортировки (имя колонки валидируется регуляркой). */
    public static function sort(?string $default = null): ?string
    {
        $sort = self::get('sort', $default);

        return is_string($sort) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $sort) ? $sort : $default;
    }

    public static function order(): string
    {
        return strtolower((string) self::get('order', 'asc')) === 'desc' ? 'DESC' : 'ASC';
    }

    public static function search(): string
    {
        return trim((string) self::get('q', ''));
    }
}
