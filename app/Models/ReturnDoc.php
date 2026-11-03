<?php

declare(strict_types=1);

namespace Uchet\Models;

use Uchet\Core\Model;

/** Шапка возврата: direction = to_supplier (ВП) | from_customer (ВК). ("Return" — reserved word, потому ReturnDoc) */
final class ReturnDoc extends Model
{
    protected string $table = 'documents_returns';

    protected array $fillable = [
        'number', 'prefix', 'direction', 'doc_date', 'warehouse_id', 'counterparty_id',
        'source_type', 'source_id', 'status', 'total_quantity', 'total_amount',
        'comment', 'posted_at', 'posted_by', 'created_by',
    ];
}
