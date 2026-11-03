<?php

declare(strict_types=1);

namespace Uchet\Repositories;

use Uchet\Core\Repository;
use Uchet\Models\Role;

/** Роли. */
final class RoleRepository extends Repository
{
    public function __construct()
    {
        parent::__construct('roles', Role::class);
    }
}
