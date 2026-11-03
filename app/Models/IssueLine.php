<?php

declare(strict_types=1);

namespace Uchet\Models;

use Uchet\Core\Model;

/** Строка табличной части РН. */
final class IssueLine extends Model
{
    protected string $table = 'documents_issues_lines';

    protected array $fillable = ['document_id', 'line_no', 'nomenclature_id', 'quantity', 'price', 'vat_rate'];
}
