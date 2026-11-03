<?php

declare(strict_types=1);

namespace Uchet\Models;

use Uchet\Core\Model;

/** Строка табличной части ПМ. */
final class MoveLine extends Model
{
    protected string $table = 'documents_moves_lines';

    protected array $fillable = ['document_id', 'line_no', 'nomenclature_id', 'quantity', 'price'];
}
