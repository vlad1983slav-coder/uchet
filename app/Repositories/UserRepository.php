<?php

declare(strict_types=1);

namespace Uchet\Repositories;

use Uchet\Core\Repository;
use Uchet\Models\User;

/** Пользователи (управление — только для admin). */
final class UserRepository extends Repository
{
    public function __construct()
    {
        parent::__construct('users', User::class);
    }

    public function allWithRoles(): array
    {
        return $this->query(
            'SELECT u.*, r.code AS role_code, r.name AS role_name
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.deleted_at IS NULL ORDER BY u.login'
        );
    }
}
