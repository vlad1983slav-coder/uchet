<?php

declare(strict_types=1);

namespace Uchet\Repositories;

use Uchet\Core\Repository;
use Uchet\Models\Unit;

/** Справочник «Единицы измерения». */
final class UnitRepository extends Repository
{
    public function __construct()
    {
        parent::__construct('catalogs_units', Unit::class);
    }
}
