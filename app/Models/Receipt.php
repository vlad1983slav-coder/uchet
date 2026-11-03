<?php

declare(strict_types=1);

namespace Uchet\Models;

use Uchet\Core\Model;

/** Шапка приходной накладной (ПН). */
final class Receipt extends Model
{
    protected string $table = 'documents_receipts';

    protected array $fillable = [
        'number', 'prefix', 'doc_date', 'warehouse_id', 'counterparty_id', 'status',
        'total_quantity', 'total_amount', 'comment', 'posted_at', 'posted_by', 'created_by',
    ];
}
