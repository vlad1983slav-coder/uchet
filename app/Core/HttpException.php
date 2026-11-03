<?php

declare(strict_types=1);

namespace Uchet\Core;

/**
 * HTTP-ошибка с кодом состояния (403, 404, 405, 419, 500 ...).
 * В index.php перехватывается и рендерится страница errors/{code}.php.
 */
class HttpException extends \RuntimeException
{
    public function __construct(
        public readonly int $statusCode,
        string $message = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : self::defaultMessage($statusCode), $statusCode, $previous);
    }

    public static function defaultMessage(int $code): string
    {
        return match ($code) {
            400 => 'Некорректный запрос',
            401 => 'Требуется авторизация',
            403 => 'Доступ запрещён',
            404 => 'Страница не найдена',
            405 => 'Метод не поддерживается',
            419 => 'Сессия устарела, обновите страницу и повторите отправку формы (CSRF)',
            422 => 'Ошибка валидации данных',
            500 => 'Внутренняя ошибка сервера',
            default => 'HTTP-ошибка ' . $code,
        };
    }
}
