<?php

declare(strict_types=1);

/**
 * Front controller — единая точка входа.
 * Все запросы (см. public/.htaccess) приходят сюда.
 */

use Uchet\Core\Auth;
use Uchet\Core\CsrfException;
use Uchet\Core\ForbiddenException;
use Uchet\Core\HttpException;
use Uchet\Core\NotFoundException;
use Uchet\Core\Request;
use Uchet\Core\Response;
use Uchet\Core\Router;
use Uchet\Core\Session;
use Uchet\Core\View;

define('ROOT', dirname(__DIR__));

// Автозагрузка: composer (vendor/autoload.php), иначе — встроенный PSR-4 загрузчик
$composerAutoload = ROOT . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
} else {
    require ROOT . '/app/Core/helpers.php';
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Uchet\\';
        if (str_starts_with($class, $prefix)) {
            $file = ROOT . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    });
}

date_default_timezone_set((string) config('app.timezone', 'UTC'));
error_reporting(E_ALL);
ini_set('display_errors', config('app.debug', true) ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT . '/storage/logs/error.log');

Session::start();

/**
 * Рендер страницы ошибки. В debug виден trace, в prod — только код.
 */
function renderError(int $code, Throwable $e): never
{
    http_response_code($code);
    $debug = (bool) config('app.debug', false);

    if ($code >= 500) {
        $log = sprintf(
            "[%s] %s: %s in %s:%d\n%s\n",
            date('c'),
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
        @file_put_contents(ROOT . '/storage/logs/error.log', $log, FILE_APPEND | LOCK_EX);
    }

    if (Request::wantsJson()) {
        Response::json([
            'ok' => false,
            'error' => [
                'code' => $code,
                'message' => ($debug || $code < 500) ? $e->getMessage() : 'Внутренняя ошибка сервера',
            ],
        ], $code);
    }

    $template = 'errors.' . $code;
    if (!View::exists($template)) {
        $template = 'errors.500';
        $code = 500;
    }
    try {
        $html = View::render($template, [
            'code' => $code,
            'message' => ($debug || $code < 500) ? $e->getMessage() : 'Внутренняя ошибка сервера',
            'exception' => $debug ? $e : null,
        ]);
    } catch (Throwable) {
        $html = '<h1>' . $code . '</h1><p>' . e(($debug || $code < 500) ? $e->getMessage() : 'Ошибка') . '</p>';
    }
    Response::html($html, $code);
}

try {
    // глобальные данные для всех шаблонов
    View::share('appName', (string) config('app.name', 'UCHET'));
    View::share('currentUser', Auth::user());
    View::share('debug', (bool) config('app.debug', false));

    Router::load(ROOT . '/config/routes.php');
    Router::dispatch();
} catch (NotFoundException $e) {
    renderError(404, $e);
} catch (ForbiddenException $e) {
    renderError(403, $e);
} catch (CsrfException $e) {
    renderError(419, $e);
} catch (HttpException $e) {
    renderError($e->statusCode, $e);
} catch (Throwable $e) {
    renderError(500, $e);
}
