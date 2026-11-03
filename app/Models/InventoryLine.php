<?php

declare(strict_types=1);

namespace Uchet\Models;

use Uchet\Core\Model;

/** Строка ТЧ инвентаризации: book_quantity (учёт) vs fact_quantity (факт), diff — генерируемая колонка. */
final class InventoryLine extends Model
{
    protected string $table = 'documents_inventories_lines';

    protected array $fillable = ['document_id', 'line_no', 'nomenclature_id', 'party_id', 'book_quantity', 'fact_quantity'];
}
