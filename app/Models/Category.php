<?php

declare(strict_types=1);

namespace Uchet\Models;

use Uchet\Core\Model;

final class Category extends Model
{
    protected string $table = 'catalogs_categories';

    protected array $fillable = ['parent_id', 'code', 'name'];
}
