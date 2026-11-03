<?php

declare(strict_types=1);

namespace Uchet\Models;

use Uchet\Core\Model;

final class Nomenclature extends Model
{
    protected string $table = 'catalogs_nomenclature';

    protected array $fillable = [
        'article', 'name', 'unit_id', 'category_id', 'vat_rate',
        'purchase_price', 'min_stock', 'is_service', 'description',
    ];
}
