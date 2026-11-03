<?php

declare(strict_types=1);

namespace Uchet\Repositories;

use Uchet\Core\Repository;
use Uchet\Models\Warehouse;

/** Справочник «Склады». */
final class WarehouseRepository extends Repository
{
    public function __construct()
    {
        parent::__construct('catalogs_warehouses', Warehouse::class);
    }

    /** @return array<int, array<string, mixed>> — склады с ФИО МОЛ */
    public function allWithMol(): array
    {
        return $this->query(
            'SELECT w.*, u.full_name AS mol_name, u.login AS mol_login
             FROM catalogs_warehouses w
             LEFT JOIN users u ON u.id = w.mol_user_id AND u.deleted_at IS NULL
             WHERE w.deleted_at IS NULL
             ORDER BY w.code'
        );
    }
}
