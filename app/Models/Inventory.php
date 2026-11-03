<?php

declare(strict_types=1);

namespace Uchet\Models;

use Uchet\Core\Model;

/** Шапка инвентаризации (ИНВ). */
final class Inventory extends Model
{
    protected string $table = 'documents_inventories';

    protected array $fillable = [
        'number', 'prefix', 'doc_date', 'warehouse_id', 'status',
        'comment', 'posted_at', 'posted_by', 'created_by',
    ];
}
