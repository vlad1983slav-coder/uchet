<?php

declare(strict_types=1);

namespace Uchet\Models;

use Uchet\Core\Model;

final class Role extends Model
{
    protected string $table = 'roles';

    protected array $fillable = ['code', 'name', 'permissions'];
}
