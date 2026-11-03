<?php

declare(strict_types=1);

namespace Uchet\Middleware;

/**
 * Middleware маршрута. handle() либо пропускает запрос дальше, либо
 * завершает его (redirect/JSON) или бросает HttpException.
 */
interface Middleware
{
    public function handle(): void;
}
