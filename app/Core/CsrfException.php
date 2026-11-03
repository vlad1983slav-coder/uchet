<?php

declare(strict_types=1);

namespace Uchet\Core;

final class CsrfException extends HttpException
{
    public function __construct(string $message = 'CSRF-токен не совпадает')
    {
        parent::__construct(419, $message);
    }
}
