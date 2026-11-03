<?php

declare(strict_types=1);

namespace Uchet\Models;

use Uchet\Core\Model;

final class Warehouse extends Model
{
    protected string $table = 'catalogs_warehouses';

    protected array $fillable = ['code', 'name', 'address', 'mol_user_id'];
}
