<?php

declare(strict_types=1);

namespace Uchet\Core;

/**
 * Формирование HTTP-ответа. Методы завершают выполнение скрипта (never).
 */
final class Response
{
    public static function html(string $content, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');
        echo $content;
        exit;
    }

    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function redirect(string $url, int $code = 302): never
    {
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://') && !str_starts_with($url, '/')) {
            $url = '/' . $url;
        }
        header('Location: ' . $url, true, $code);
        exit;
    }

    /** Отдача файла (печатная форма PDF и т.п.). */
    public static function download(string $content, string $filename, string $mime = 'application/octet-stream', bool $inline = false): never
    {
        http_response_code(200);
        header('Content-Type: ' . $mime);
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . rawurlencode($filename) . '"');
        header('Content-Length: ' . (string) strlen($content));
        header('Cache-Control: no-store');
        echo $content;
        exit;
    }

    public static function noContent(): never
    {
        http_response_code(204);
        exit;
    }
}
