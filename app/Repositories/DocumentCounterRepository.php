<?php

declare(strict_types=1);

namespace Uchet\Repositories;

use Uchet\Core\Database;
use Uchet\Core\Repository;

/** Счётчики нумерации документов document_counters (префикс + год). */
final class DocumentCounterRepository extends Repository
{
    public function __construct()
    {
        parent::__construct('document_counters', '', false);
    }

    /**
     * Атомарно увеличить счётчик и вернуть новое значение (внутри транзакции — безопасно
     * при параллельном создании документов).
     */
    public function increment(string $prefix, int $year): int
    {
        $this->execute(
            'INSERT INTO document_counters (prefix, year, last_number) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE last_number = LAST_INSERT_ID(last_number + 1)',
            [$prefix, $year]
        );

        return (int) Database::connection()->lastInsertId();
    }
}
