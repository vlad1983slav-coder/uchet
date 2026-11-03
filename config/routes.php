<?php

declare(strict_types=1);

use Uchet\Core\Router;

/**
 * Таблица маршрутов. Формат: Router::метод('/uri/{param}', 'Контроллер@метод', [middleware], 'имя');
 * Middleware: 'auth' | 'guest' | 'csrf' (классы в Uchet\Middleware\).
 * Именованные маршруты строят URL через url('имя', ['id' => 1]).
 *
 * Ниже — маршруты ядра (авторизация). Маршруты справочников, документов
 * и отчётов добавляются по мере реализации контроллеров (п. 5 ТЗ).
 */

Router::get('/', 'DashboardController@index', ['auth'], 'home');

Router::group(['middleware' => ['guest']], static function (): void {
    Router::get('/login', 'AuthController@showLogin', [], 'login');
    Router::post('/login', 'AuthController@login', ['csrf'], 'login.post');
});

Router::post('/logout', 'AuthController@logout', ['auth', 'csrf'], 'logout');
