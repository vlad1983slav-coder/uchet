<?php

declare(strict_types=1);

namespace Uchet\Models;

use Uchet\Core\Model;

/** Шапка перемещения между складами (ПМ). */
final class Move extends Model
{
    protected string $table = 'documents_moves';

    protected array $fillable = [
        'number', 'prefix', 'doc_date', 'warehouse_from_id', 'warehouse_to_id', 'status',
        'total_quantity', 'total_amount', 'comment', 'posted_at', 'posted_by', 'created_by',
    ];
}
