<?php

declare(strict_types=1);

namespace Uchet\Models;

use Uchet\Core\Model;

/** Строка табличной части возврата. */
final class ReturnLine extends Model
{
    protected string $table = 'documents_returns_lines';

    protected array $fillable = ['document_id', 'line_no', 'nomenclature_id', 'quantity', 'price', 'vat_rate'];
}
