<?php

declare(strict_types=1);

namespace Uchet\Controllers;

use Uchet\Core\Repository;
use Uchet\Repositories\CategoryRepository;

/** Справочник «Категории номенклатуры» (иерархический). */
final class CategoryController extends CatalogController
{
    protected function repo(): Repository
    {
        return new CategoryRepository();
    }

    protected function titlePlural(): string
    {
        return 'Категории номенклатуры';
    }

    protected function routeBase(): string
    {
        return 'catalogs.categories';
    }

    protected function searchColumn(): string
    {
        return 'name';
    }

    public function index(): void
    {
        $this->authorize('catalogs.read');
        $rows = (new CategoryRepository())->allWithParent();
        $this->render('catalogs.index', [
            'title' => $this->titlePlural(),
            'routeBase' => $this->routeBase(),
            'columns' => [
                ['key' => 'code', 'label' => 'Код'],
                ['key' => 'name', 'label' => 'Наименование'],
                ['key' => 'parent_name', 'label' => 'Родительская группа'],
            ],
            'rows' => $rows,
            'pagination' => ['total' => count($rows), 'page' => 1, 'per_page' => count($rows), 'last_page' => 1],
            'q' => '',
            'canCreate' => \Uchet\Core\Auth::can('catalogs.create'),
            'canDelete' => \Uchet\Core\Auth::can('catalogs.delete'),
        ]);
    }

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Код'],
            ['key' => 'name', 'label' => 'Наименование'],
        ];
    }

    protected function fields(): array
    {
        $cats = array_column((new CategoryRepository())->allWithParent(), 'name', 'id');

        return [
            ['name' => 'code', 'label' => 'Код', 'type' => 'text', 'required' => true, 'maxlength' => 32],
            ['name' => 'name', 'label' => 'Наименование', 'type' => 'text', 'required' => true, 'maxlength' => 255],
            ['name' => 'parent_id', 'label' => 'Родительская группа', 'type' => 'select', 'options' => $cats, 'required' => false, 'empty' => '— верхний уровень —'],
        ];
    }

    protected function rules(?int $excludeId = null): array
    {
        return [
            'code' => 'required|string|max:32|unique:catalogs_categories,code,' . ($excludeId ?? 'NULL') . ',id',
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|integer|exists:catalogs_categories,id',
        ];
    }
}
