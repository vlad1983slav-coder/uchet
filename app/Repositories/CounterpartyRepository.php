<?php

declare(strict_types=1);

namespace Uchet\Repositories;

use Uchet\Core\Repository;
use Uchet\Models\Counterparty;

/** Справочник «Контрагенты». */
final class CounterpartyRepository extends Repository
{
    public function __construct()
    {
        parent::__construct('catalogs_counterparties', Counterparty::class);
    }

    /**
     * Поиск контрагентов по ИНН/наименованию.
     * $type: 'supplier' | 'customer' | null — любой (type='both' подходит всегда).
     */
    public function search(string $q, ?string $type = null, int $limit = 20): array
    {
        $where = '(inn LIKE ? OR name LIKE ?)';
        $params = ['%' . $q . '%', '%' . $q . '%'];
        if ($type === 'supplier' || $type === 'customer') {
            $where .= " AND (type = ? OR type = 'both')";
            $params[] = $type;
        }

        return $this->query(
            'SELECT * FROM catalogs_counterparties WHERE deleted_at IS NULL AND ' . $where
            . ' ORDER BY name LIMIT ' . max(1, $limit),
            $params
        );
    }
}
