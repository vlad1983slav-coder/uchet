<?php

declare(strict_types=1);

namespace Uchet\Services;

use Uchet\Repositories\DocumentCounterRepository;

/**
 * Нумерация документов вида ПН-000001: префикс + год + порядковый номер.
 * Счётчик увеличивается атомарно (INSERT ... ON DUPLICATE KEY UPDATE LAST_INSERT_ID()),
 * поэтому параллельное создание документов не даст дубликатов (внутри транзакции).
 */
final class NumberingService
{
    public function __construct(
        private readonly DocumentCounterRepository $counters = new DocumentCounterRepository(),
    ) {}

    public function nextNumber(string $prefix, string $docDate): string
    {
        $year = (int) date('Y', strtotime($docDate) ?: time());
        $n = $this->counters->increment($prefix, $year);

        return $prefix . '-' . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }
}
