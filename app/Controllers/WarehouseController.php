<?php

declare(strict_types=1);

namespace Uchet\Controllers;

use Uchet\Core\Repository;
use Uchet\Repositories\UserRepository;
use Uchet\Repositories\WarehouseRepository;

/** Справочник «Склады» (код, наименование, адрес, МОЛ). */
final class WarehouseController extends CatalogController
{
    protected function repo(): Repository
    {
        return new WarehouseRepository();
    }

    protected function titlePlural(): string
    {
        return 'Склады';
    }

    protected function routeBase(): string
    {
        return 'catalogs.warehouses';
    }

    protected function searchColumn(): string
    {
        return 'name';
    }

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Код', 'sortable' => true],
            ['key' => 'name', 'label' => 'Наименование', 'sortable' => true],
            ['key' => 'address', 'label' => 'Адрес'],
        ];
    }

    protected function fields(): array
    {
        $mols = array_column((new UserRepository())->allWithRoles(), 'full_name', 'id');

        return [
            ['name' => 'code', 'label' => 'Код', 'type' => 'text', 'required' => true, 'maxlength' => 16],
            ['name' => 'name', 'label' => 'Наименование', 'type' => 'text', 'required' => true, 'maxlength' => 255],
            ['name' => 'address', 'label' => 'Адрес', 'type' => 'text', 'maxlength' => 500],
            ['name' => 'mol_user_id', 'label' => 'МОЛ (материально ответственное лицо)', 'type' => 'select', 'options' => $mols, 'required' => false, 'empty' => '— не назначен —'],
        ];
    }

    protected function rules(?int $excludeId = null): array
    {
        return [
            'code' => 'required|string|max:16|unique:catalogs_warehouses,code,' . ($excludeId ?? 'NULL') . ',id',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'mol_user_id' => 'nullable|integer|exists:users,id',
        ];
    }
}
