<?php

declare(strict_types=1);

namespace Uchet\Controllers;

use Uchet\Core\Repository;
use Uchet\Repositories\UnitRepository;

/** Справочник «Единицы измерения» (код ОКЕИ + коэффициент пересчёта). */
final class UnitController extends CatalogController
{
    protected function repo(): Repository
    {
        return new UnitRepository();
    }

    protected function titlePlural(): string
    {
        return 'Единицы измерения';
    }

    protected function routeBase(): string
    {
        return 'catalogs.units';
    }

    protected function searchColumn(): string
    {
        return 'name';
    }

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Код (ОКЕИ)', 'sortable' => true],
            ['key' => 'name', 'label' => 'Наименование', 'sortable' => true],
            ['key' => 'coefficient', 'label' => 'Коэффициент'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'code', 'label' => 'Код (ОКЕИ)', 'type' => 'text', 'required' => true, 'maxlength' => 16],
            ['name' => 'name', 'label' => 'Наименование', 'type' => 'text', 'required' => true, 'maxlength' => 100],
            ['name' => 'coefficient', 'label' => 'Коэффициент пересчёта в базовую единицу', 'type' => 'number', 'step' => '0.000001', 'min' => 0, 'default' => 1, 'required' => true],
        ];
    }

    protected function rules(?int $excludeId = null): array
    {
        return [
            'code' => 'required|string|max:16|unique:catalogs_units,code,' . ($excludeId ?? 'NULL') . ',id',
            'name' => 'required|string|max:100',
            'coefficient' => 'required|numeric|min:0',
        ];
    }
}
