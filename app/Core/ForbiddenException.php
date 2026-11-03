<?php

declare(strict_types=1);

namespace Uchet\Core;

final class ForbiddenException extends HttpException
{
    public function __construct(string $message = 'Доступ запрещён')
    {
        parent::__construct(403, $message);
    }
}
