<?php

declare(strict_types=1);

namespace Uchet\Models;

use Uchet\Core\Model;

/** Строка табличной части ПН (amount/vat_amount — генерируемые колонки БД). */
final class ReceiptLine extends Model
{
    protected string $table = 'documents_receipts_lines';

    protected array $fillable = ['document_id', 'line_no', 'nomenclature_id', 'quantity', 'price', 'vat_rate'];
}
