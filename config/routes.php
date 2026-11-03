<?php

declare(strict_types=1);

use Uchet\Core\Router;

/**
 * Таблица маршрутов.
 * Формат: Router::метод('/uri/{param}', 'Контроллер@метод', [middleware], 'имя');
 * Middleware: 'auth' | 'guest' | 'csrf' (классы Uchet\Middleware\...).
 * Права проверяются внутри контроллеров через authorize('resource.action').
 */

// --- вход/выход ---
Router::get('/', 'DashboardController@index', ['auth'], 'home');
Router::group(['middleware' => ['guest']], static function (): void {
    Router::get('/login', 'AuthController@showLogin', [], 'login');
    Router::post('/login', 'AuthController@login', ['csrf'], 'login.post');
});
Router::post('/logout', 'AuthController@logout', ['auth', 'csrf'], 'logout');

// --- AJAX-поиск для табличных частей и форм ---
Router::group(['prefix' => '/ajax', 'middleware' => ['auth']], static function (): void {
    Router::get('/nomenclature', 'NomenclatureController@search', [], 'ajax.nomenclature');
    Router::get('/nomenclature/{id}', 'NomenclatureController@showAjax', [], 'ajax.nomenclature.show');
    Router::get('/counterparties', 'CounterpartyController@search', [], 'ajax.counterparties');
});

// --- справочники ---
Router::group(['prefix' => '/catalogs', 'middleware' => ['auth']], static function (): void {
    $catalogs = [
        'nomenclature' => 'NomenclatureController',
        'warehouses' => 'WarehouseController',
        'counterparties' => 'CounterpartyController',
        'units' => 'UnitController',
        'categories' => 'CategoryController',
    ];
    foreach ($catalogs as $slug => $controller) {
        $base = 'catalogs.' . $slug;
        Router::get('/' . $slug, $controller . '@index', [], $base . '.index');
        Router::get('/' . $slug . '/create', $controller . '@create', [], $base . '.create');
        Router::post('/' . $slug, $controller . '@store', ['csrf'], $base . '.store');
        Router::get('/' . $slug . '/{id}/edit', $controller . '@edit', [], $base . '.edit');
        Router::post('/' . $slug . '/{id}/update', $controller . '@update', ['csrf'], $base . '.update');
        Router::post('/' . $slug . '/{id}/delete', $controller . '@destroy', ['csrf'], $base . '.destroy');
    }
});

// --- документы: ПН / РН / ПМ / ИНВ / возвраты ---
Router::group(['prefix' => '/documents', 'middleware' => ['auth']], static function (): void {
    $docs = [
        'receipts' => 'ReceiptController',
        'issues' => 'IssueController',
        'moves' => 'MoveController',
        'inventories' => 'InventoryController',
        'returns' => 'ReturnController',
    ];
    foreach ($docs as $slug => $controller) {
        $base = $slug; // имя маршрута = slug, напр. receipts.show
        Router::get('/' . $slug, $controller . '@index', [], $base . '.index');
        Router::get('/' . $slug . '/create', $controller . '@create', [], $base . '.create');
        Router::post('/' . $slug, $controller . '@store', ['csrf'], $base . '.store');
        Router::get('/' . $slug . '/{id}', $controller . '@show', [], $base . '.show');
        Router::get('/' . $slug . '/{id}/edit', $controller . '@edit', [], $base . '.edit');
        Router::post('/' . $slug . '/{id}/update', $controller . '@update', ['csrf'], $base . '.update');
        Router::post('/' . $slug . '/{id}/post', $controller . '@post', ['csrf'], $base . '.post');
        Router::post('/' . $slug . '/{id}/unpost', $controller . '@unpost', ['csrf'], $base . '.unpost');
        Router::post('/' . $slug . '/{id}/delete', $controller . '@destroy', ['csrf'], $base . '.destroy');
        Router::get('/' . $slug . '/{id}/print', $controller . '@print', [], $base . '.print');
    }
    // обновить учётные количества черновика инвентаризации по остаткам склада
    Router::post('/inventories/{id}/refill', 'InventoryController@refill', ['csrf'], 'inventories.refill');
});

// --- отчёты ---
Router::group(['prefix' => '/reports', 'middleware' => ['auth']], static function (): void {
    Router::get('/stock', 'ReportController@stock', [], 'reports.stock');
    Router::get('/movements', 'ReportController@movements', [], 'reports.movements');
    Router::get('/turnover', 'ReportController@turnover', [], 'reports.turnover');
    Router::get('/card/{id}', 'ReportController@card', [], 'reports.card');
    Router::get('/settlements', 'ReportController@settlements', [], 'reports.settlements');
});

// --- пользователи (admin) ---
Router::group(['prefix' => '/users', 'middleware' => ['auth']], static function (): void {
    Router::get('/', 'UserController@index', [], 'users.index');
    Router::get('/create', 'UserController@create', [], 'users.create');
    Router::post('/', 'UserController@store', ['csrf'], 'users.store');
    Router::get('/{id}/edit', 'UserController@edit', [], 'users.edit');
    Router::post('/{id}/update', 'UserController@update', ['csrf'], 'users.update');
    Router::post('/{id}/delete', 'UserController@destroy', ['csrf'], 'users.destroy');
});
