<?php

declare(strict_types=1);

namespace Uchet\Repositories;

use Uchet\Models\ReturnDoc;
use Uchet\Models\ReturnLine;

/** Возвраты: ВП (поставщику) / ВК (от покупателя). */
final class ReturnRepository extends DocumentRepository
{
    protected array $sortable = [
        'id', 'number', 'doc_date', 'status', 'direction', 'total_quantity', 'total_amount',
        'warehouse_id', 'counterparty_id', 'created_at', 'updated_at',
    ];

    public function __construct()
    {
        parent::__construct('documents_returns', 'documents_returns_lines', ReturnDoc::class, ReturnLine::class, 'ВП');
    }

    /**
     * @param array{q?: string, status?: string, direction?: string, warehouse_id?: int|string, counterparty_id?: int|string, date_from?: string, date_to?: string} $filters
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
        if (!empty($filters['direction'])) {
            $where['direction'] = (string) $filters['direction'];
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
