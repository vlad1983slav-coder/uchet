<?php

declare(strict_types=1);

namespace Uchet\Models;

use Uchet\Core\Model;

/** Партия товара (документ-основание партионного учёта). */
final class StockParty extends Model
{
    protected string $table = 'stock_parties';

    protected string $primaryKey = 'id';

    protected array $fillable = ['nomenclature_id', 'source_type', 'source_id'];
}
