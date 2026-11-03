<?php

declare(strict_types=1);

/**
 * Конфигурация приложения. Переопределяется через переменные окружения
 * (удобно для .env / docker): DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD, APP_URL, APP_DEBUG.
 */
return [
    'app' => [
        'name' => 'UCHET',
        'debug' => filter_var(env('APP_DEBUG', 'true'), FILTER_VALIDATE_BOOLEAN),
        // '' — относительные URL; задайте полный URL, если нужны абсолютные ссылки
        'base_url' => rtrim((string) env('APP_URL', ''), '/'),
        'timezone' => (string) env('APP_TIMEZONE', 'Asia/Krasnoyarsk'),
        'currency' => 'RUB',
    ],
    'db' => [
        'host' => (string) env('DB_HOST', '127.0.0.1'),
        'port' => (int) env('DB_PORT', 3306),
        'name' => (string) env('DB_DATABASE', 'uchet'),
        'user' => (string) env('DB_USERNAME', 'root'),
        'pass' => (string) env('DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
    ],
    'session' => [
        'name' => 'UCHETSESSID',
        'lifetime' => 7200,
        'path' => dirname(__DIR__) . '/storage/sessions',
    ],
    'security' => [
        'password_algo' => PASSWORD_ARGON2ID,
        'csrf_field' => '_csrf',
    ],
];
