<?php

declare(strict_types=1);

namespace Uchet\Core;

/**
 * Сессии PHP с безопасными cookie (HttpOnly, SameSite=Lax, strict mode)
 * и flash-сообщениями в стиле Laravel (new/old).
 */
final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;

            return;
        }

        $cfg = (array) config('session', []);
        $path = (string) ($cfg['path'] ?? ROOT . '/storage/sessions');
        if (is_dir($path) && is_writable($path)) {
            session_save_path($path);
        }

        session_name((string) ($cfg['name'] ?? 'UCHETSESSID'));
        session_set_cookie_params([
            'lifetime' => (int) ($cfg['lifetime'] ?? 7200),
            'path' => '/',
            'domain' => '',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_probability', '1');

        session_start();
        self::$started = true;
        self::ageFlash();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    // --- flash-сообщения ---

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash_new'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_flash_new'][$key] ?? $_SESSION['_flash_old'][$key] ?? $default;
    }

    /** Сохранить введённые данные формы для repopulation после редиректа. */
    public static function flashInput(array $input): void
    {
        self::flash('_old_input', $input);
    }

    public static function getOld(string $key, mixed $default = null): mixed
    {
        $old = self::getFlash('_old_input', []);

        return is_array($old) && array_key_exists($key, $old) ? $old[$key] : $default;
    }

    /** Вызывается при старте сессии: "новые" flash становятся "старыми", старые удаляются. */
    private static function ageFlash(): void
    {
        $_SESSION['_flash_old'] = $_SESSION['_flash_new'] ?? [];
        unset($_SESSION['_flash_new']);
    }
}
