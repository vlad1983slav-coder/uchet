<?php

declare(strict_types=1);

namespace Uchet\Core;

/**
 * Рендер PHP-шаблонов из resources/views/.
 * Шаблон 'documents.receipts.index' → resources/views/documents/receipts/index.php.
 * make() вкладывает view в layout (внутри layout доступна переменная $content).
 */
final class View
{
    /** @var array<string, mixed> — данные, доступные во всех шаблонах */
    private static array $shared = [];

    private static ?string $viewsPath = null;

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function exists(string $template): bool
    {
        return is_file(self::path($template));
    }

    private static function path(string $template): string
    {
        self::$viewsPath ??= ROOT . '/resources/views/';

        return self::$viewsPath . str_replace('.', '/', $template) . '.php';
    }

    /** @param array<string, mixed> $data */
    public static function render(string $template, array $data = []): string
    {
        $file = self::path($template);
        if (!is_file($file)) {
            throw new \RuntimeException("Шаблон не найден: {$template} ({$file})");
        }

        $data = array_merge(self::$shared, $data);
        extract($data, EXTR_SKIP);

        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }

    /**
     * View + layout: содержимое шаблона попадает в переменную $content внутри layout.
     *
     * @param array<string, mixed> $data
     */
    public static function make(string $template, array $data = [], string $layout = 'layouts.main'): string
    {
        $data['content'] = self::render($template, $data);

        return self::render($layout, $data);
    }
}
