<?php

declare(strict_types=1);

namespace Uchet\Repositories;

use Uchet\Models\Move;
use Uchet\Models\MoveLine;

/** Перемещения между складами (ПМ). */
final class MoveRepository extends DocumentRepository
{
    protected array $sortable = [
        'id', 'number', 'doc_date', 'status', 'total_quantity', 'total_amount',
        'warehouse_from_id', 'warehouse_to_id', 'created_at', 'updated_at',
    ];

    public function __construct()
    {
        parent::__construct('documents_moves', 'documents_moves_lines', Move::class, MoveLine::class, 'ПМ');
    }

    /**
     * Список ПМ. Фильтр warehouse_id означает «склад-источник ИЛИ склад-получатель».
     *
     * @param array{q?: string, status?: string, warehouse_id?: int|string, date_from?: string, date_to?: string} $filters
     */
    public function listDocuments(array $filters, int $page, int $perPage, string $sort = 'doc_date', string $order = 'DESC'): array
    {
        $where = [];
        $extraSql = '';
        $extraParams = [];
        if (!empty($filters['q'])) {
            $where[] = ['number', 'LIKE', '%' . $filters['q'] . '%'];
        }
        if (!empty($filters['status'])) {
            $where['status'] = (string) $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = ['doc_date', '>=', $filters['date_from'] . ' 00:00:00'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = ['doc_date', '<=', $filters['date_to'] . ' 23:59:59'];
        }
        if (!empty($filters['warehouse_id'])) {
            $wh = (int) $filters['warehouse_id'];
            $extraSql = ' AND (warehouse_from_id = ? OR warehouse_to_id = ?)';
            $extraParams = [$wh, $wh];
        }
        if (!in_array($sort, $this->sortable, true)) {
            $sort = 'doc_date';
        }

        [$whereSql, $params] = $this->buildWhere($where);
        $from = 'FROM ' . $this->q($this->table) . ' WHERE 1=1' . $this->deletedCondition() . $whereSql . $extraSql;

        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $countStmt = $this->pdo()->prepare('SELECT COUNT(*) ' . $from);
        $countStmt->execute([...$params, ...$extraParams]);
        $total = (int) $countStmt->fetchColumn();

        $sql = 'SELECT * ' . $from
            . ' ORDER BY ' . $this->q($sort) . ' ' . ($order === 'DESC' ? 'DESC' : 'ASC')
            . ' LIMIT ? OFFSET ?';
        $stmt = $this->pdo()->prepare($sql);
        $i = 1;
        foreach ([...$params, ...$extraParams] as $value) {
            $stmt->bindValue($i++, $value);
        }
        $stmt->bindValue($i++, $perPage, \PDO::PARAM_INT);
        $stmt->bindValue($i, ($page - 1) * $perPage, \PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => array_map(fn (array $row) => $this->hydrate($row), $stmt->fetchAll()),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }
}
