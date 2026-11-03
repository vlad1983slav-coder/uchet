<?php

declare(strict_types=1);

namespace Uchet\Models;

use Uchet\Core\Model;

final class Counterparty extends Model
{
    protected string $table = 'catalogs_counterparties';

    protected array $fillable = ['inn', 'kpp', 'name', 'type', 'phone', 'email', 'address'];
}
