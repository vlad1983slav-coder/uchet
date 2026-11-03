<?php

declare(strict_types=1);

namespace Uchet\Models;

use Uchet\Core\Model;

/** Строка регистра движений (приход/расход по документу). */
final class Movement extends Model
{
    protected string $table = 'registers_movements';

    protected array $fillable = [
        'document_type', 'document_id', 'doc_date', 'warehouse_id', 'nomenclature_id',
        'party_id', 'quantity_in', 'quantity_out', 'amount_in', 'amount_out',
    ];
}
