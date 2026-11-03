<?php

declare(strict_types=1);

namespace Uchet\Models;

use Uchet\Core\Model;

final class User extends Model
{
    protected string $table = 'users';

    protected array $fillable = ['login', 'email', 'password_hash', 'full_name', 'role_id', 'is_active', 'last_login_at'];
}
