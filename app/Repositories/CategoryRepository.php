<?php

declare(strict_types=1);

namespace Uchet\Repositories;

use Uchet\Core\Repository;
use Uchet\Models\Category;

/** Справочник «Категории номенклатуры» (иерархия). */
final class CategoryRepository extends Repository
{
    public function __construct()
    {
        parent::__construct('catalogs_categories', Category::class);
    }

    /** Плоский список с именем родителя для выпадающих списков. */
    public function allWithParent(): array
    {
        return $this->query(
            'SELECT c.*, p.name AS parent_name
             FROM catalogs_categories c
             LEFT JOIN catalogs_categories p ON p.id = c.parent_id AND p.deleted_at IS NULL
             WHERE c.deleted_at IS NULL
             ORDER BY c.name'
        );
    }
}
