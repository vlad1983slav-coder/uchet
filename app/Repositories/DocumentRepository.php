<?php

declare(strict_types=1);

namespace Uchet\Repositories;

use Uchet\Core\Database;
use Uchet\Core\Repository;

/**
 * Базовый репозиторий документа «шапка + табличная часть» (в стиле 1С).
 * Знает: чтение шапки со строками, replaceLines(), пересчёт итогов,
 * список с фильтрами (поиск по номеру, статус, склад, период дат).
 */
abstract class DocumentRepository extends Repository
{
    /** @var string[] колонки шапки, по которым разрешена сортировка списка */
    protected array $sortable = [
        'id', 'number', 'doc_date', 'status', 'total_quantity', 'total_amount',
        'warehouse_id', 'counterparty_id', 'created_at', 'updated_at',
    ];

    public function __construct(
        string $table,
        protected readonly string $linesTable,
        string $modelClass,
        protected readonly string $linesModelClass = '',
        protected readonly string $defaultPrefix = '',
    ) {
        parent::__construct($table, $modelClass);
    }

    public function linesTableName(): string
    {
        return $this->linesTable;
    }

    protected function hydrateLine(array $row): object
    {
        if ($this->linesModelClass !== '' && class_exists($this->linesModelClass)) {
            $class = $this->linesModelClass;

            return $class::fromRow($row);
        }

        return (object) $row;
    }

    /** @return object[] — строки ТЧ документа */
    public function lines(int $documentId): array
    {
        $rows = $this->query(
            'SELECT * FROM ' . $this->q($this->linesTable)
            . ' WHERE document_id = ? AND deleted_at IS NULL ORDER BY line_no, id',
            [$documentId]
        );

        return array_map(fn (array $row) => $this->hydrateLine($row), $rows);
    }

    /** @return array{header: object, lines: object[]}|null */
    public function findWithLines(int $id): ?array
    {
        $header = $this->find($id);
        if ($header === null) {
            return null;
        }

        return ['header' => $header, 'lines' => $this->lines($id)];
    }

    /**
     * Полная замена табличной части (в транзакции вызывающего):
     * старые строки — soft delete, новые — вставка с перенумерацией line_no.
     *
     * @param array<int, array<string, mixed>> $lines
     */
    public function replaceLines(int $documentId, array $lines): void
    {
        $this->execute(
            'UPDATE ' . $this->q($this->linesTable) . ' SET deleted_at = NOW(), updated_at = NOW() WHERE document_id = ? AND deleted_at IS NULL',
            [$documentId]
        );
        $lineNo = 1;
        foreach ($lines as $line) {
            unset($line['id'], $line['amount'], $line['vat_amount'], $line['diff_quantity'], $line['created_at'], $line['updated_at'], $line['deleted_at']);
            $line['document_id'] = $documentId;
            $line['line_no'] = $lineNo++;
            $this->insertLine($line);
        }
    }

    /** @param array<string, mixed> $data */
    protected function insertLine(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $data['created_at'] = $now;
        $data['updated_at'] = $now;
        $columns = array_keys($data);
        $sql = 'INSERT INTO ' . $this->q($this->linesTable)
            . ' (' . implode(', ', array_map(fn (string $c) => $this->q($c), $columns)) . ')'
            . ' VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $this->execute($sql, array_values($data));

        return (int) Database::lastInsertId();
    }

    /**
     * Пересчёт итогов шапки из табличной части (total_quantity / total_amount).
     * У документов без этих колонок (ИНВ) — только updated_at.
     */
    public function recalcTotals(int $id): void
    {
        $sets = [];
        if ($this->hasColumn('total_quantity')) {
            $sets[] = 't.total_quantity = COALESCE((SELECT SUM(l.quantity) FROM ' . $this->q($this->linesTable)
                . ' l WHERE l.document_id = t.id AND l.deleted_at IS NULL), 0)';
        }
        if ($this->hasColumn('total_amount')) {
            $sets[] = 't.total_amount = COALESCE((SELECT SUM(l.amount) FROM ' . $this->q($this->linesTable)
                . ' l WHERE l.document_id = t.id AND l.deleted_at IS NULL), 0)';
        }
        $sets[] = 't.updated_at = NOW()';
        $this->execute(
            'UPDATE ' . $this->q($this->table) . ' t SET ' . implode(', ', $sets) . ' WHERE t.id = ?',
            [$id]
        );
    }

    /**
     * Список документов с фильтрами в духе журнала 1С.
     *
     * @param array{q?: string, status?: string, warehouse_id?: int|string, counterparty_id?: int|string, date_from?: string, date_to?: string} $filters
     * @return array{data: object[], total: int, page: int, per_page: int, last_page: int}
     */
    public function listDocuments(array $filters, int $page, int $perPage, string $sort = 'doc_date', string $order = 'DESC'): array
    {
        $where = [];
        if (!empty($filters['q'])) {
            $where[] = ['number', 'LIKE', '%' . $filters['q'] . '%'];
        }
        if (!empty($filters['status'])) {
            $where['status'] = (string) $filters['status'];
        }
        if (!empty($filters['warehouse_id'])) {
            $where['warehouse_id'] = (int) $filters['warehouse_id'];
        }
        if (!empty($filters['counterparty_id'])) {
            $where['counterparty_id'] = (int) $filters['counterparty_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = ['doc_date', '>=', $filters['date_from'] . ' 00:00:00'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = ['doc_date', '<=', $filters['date_to'] . ' 23:59:59'];
        }
        if (!in_array($sort, $this->sortable, true)) {
            $sort = 'doc_date';
        }

        return $this->paginate($page, $perPage, $where, $sort, $order);
    }
}
