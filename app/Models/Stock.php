<?php

declare(strict_types=1);

namespace Uchet\Models;

use Uchet\Core\Model;

/** Строка регистра остатков: склад + номенклатура + партия. */
final class Stock extends Model
{
    protected string $table = 'registers_stock';

    protected array $fillable = ['warehouse_id', 'nomenclature_id', 'party_id', 'quantity', 'cost'];
}
