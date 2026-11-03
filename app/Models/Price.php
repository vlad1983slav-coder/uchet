<?php

declare(strict_types=1);

namespace Uchet\Models;

use Uchet\Core\Model;

/** Строка регистра цен. */
final class Price extends Model
{
    protected string $table = 'registers_prices';

    protected array $fillable = ['nomenclature_id', 'price_type', 'price', 'date_from'];
}
