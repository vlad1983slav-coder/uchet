<?php

declare(strict_types=1);

namespace Uchet\Models;

use Uchet\Core\Model;

final class Unit extends Model
{
    protected string $table = 'catalogs_units';

    protected array $fillable = ['code', 'name', 'coefficient'];
}
