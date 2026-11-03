<?php

declare(strict_types=1);

namespace Uchet\Repositories;

use Uchet\Models\Inventory;
use Uchet\Models\InventoryLine;

/** Инвентаризации (ИНВ). */
final class InventoryRepository extends DocumentRepository
{
    protected array $sortable = ['id', 'number', 'doc_date', 'status', 'warehouse_id', 'created_at', 'updated_at'];

    public function __construct()
    {
        parent::__construct('documents_inventories', 'documents_inventories_lines', Inventory::class, InventoryLine::class, 'ИНВ');
    }
}
