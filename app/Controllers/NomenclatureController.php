<?php

declare(strict_types=1);

namespace Uchet\Controllers;

use Uchet\Core\Auth;
use Uchet\Core\Request;
use Uchet\Repositories\CategoryRepository;
use Uchet\Repositories\NomenclatureRepository;
use Uchet\Repositories\PriceRepository;
use Uchet\Repositories\UnitRepository;

/** Справочник «Номенклатура» + AJAX-поиск для табличных частей документов. */
final class NomenclatureController extends CatalogController
{
    private function nomRepo(): NomenclatureRepository
    {
        return new NomenclatureRepository();
    }

    protected function repo(): \Uchet\Core\Repository
    {
        return $this->nomRepo();
    }

    protected function titlePlural(): string
    {
        return 'Номенклатура';
    }

    protected function routeBase(): string
    {
        return 'catalogs.nomenclature';
    }

    protected function searchColumn(): string
    {
        return 'article';
    }

    public function index(): void
    {
        $this->authorize('catalogs.read');
        $q = Request::search();
        $result = $this->nomRepo()->listWithRefs(
            Request::page(),
            Request::perPage(20),
            $q,
            in_array(Request::sort('article'), ['article', 'name', 'purchase_price', 'min_stock'], true) ? Request::sort('article') : 'article',
            Request::order() === 'DESC' ? 'DESC' : 'ASC'
        );
        $this->render('catalogs.index', [
            'title' => $this->titlePlural(),
            'routeBase' => $this->routeBase(),
            'columns' => [
                ['key' => 'article', 'label' => 'Артикул', 'sortable' => true],
                ['key' => 'name', 'label' => 'Наименование', 'sortable' => true],
                ['key' => 'unit_name', 'label' => 'Ед.изм.'],
                ['key' => 'category_name', 'label' => 'Категория'],
                ['key' => 'vat_rate', 'label' => 'НДС,%'],
                ['key' => 'purchase_price', 'label' => 'Учётная цена', 'sortable' => true],
                ['key' => 'min_stock', 'label' => 'Мин. остаток'],
                ['key' => 'is_service', 'label' => 'Услуга'],
            ],
            'rows' => array_map(fn ($m) => $m->toArray(), $result['data']),
            'pagination' => $result,
            'q' => $q,
            'canCreate' => Auth::can('catalogs.create'),
            'canDelete' => Auth::can('catalogs.delete'),
        ]);
    }

    protected function columns(): array
    {
        return [
            ['key' => 'article', 'label' => 'Артикул', 'sortable' => true],
            ['key' => 'name', 'label' => 'Наименование', 'sortable' => true],
        ];
    }

    protected function fields(): array
    {
        $units = array_column((new UnitRepository())->all([], 'code'), 'name', 'id');
        $cats = array_column((new CategoryRepository())->allWithParent(), 'name', 'id');

        return [
            ['name' => 'article', 'label' => 'Артикул', 'type' => 'text', 'required' => true, 'maxlength' => 64],
            ['name' => 'name', 'label' => 'Наименование', 'type' => 'text', 'required' => true, 'maxlength' => 500],
            ['name' => 'unit_id', 'label' => 'Единица измерения', 'type' => 'select', 'options' => $units, 'required' => true],
            ['name' => 'category_id', 'label' => 'Категория', 'type' => 'select', 'options' => $cats, 'required' => false, 'empty' => '— без категории —'],
            ['name' => 'vat_rate', 'label' => 'Ставка НДС, %', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'max' => 100, 'default' => 20, 'required' => true],
            ['name' => 'purchase_price', 'label' => 'Учётная (закупочная) цена', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'default' => 0],
            ['name' => 'min_stock', 'label' => 'Минимальный остаток', 'type' => 'number', 'step' => '0.001', 'min' => 0, 'default' => 0],
            ['name' => 'is_service', 'label' => 'Услуга (без складского учёта)', 'type' => 'checkbox'],
            ['name' => 'description', 'label' => 'Описание', 'type' => 'textarea'],
        ];
    }

    protected function rules(?int $excludeId = null): array
    {
        $unique = 'unique:catalogs_nomenclature,article,' . ($excludeId ?? 'NULL') . ',id';

        return [
            'article' => 'required|string|max:64|' . $unique,
            'name' => 'required|string|max:500',
            'unit_id' => 'required|integer|exists:catalogs_units,id',
            'category_id' => 'nullable|integer|exists:catalogs_categories,id',
            'vat_rate' => 'required|numeric|min:0|max:100',
            'purchase_price' => 'nullable|numeric|min:0',
            'min_stock' => 'nullable|numeric|min:0',
            'is_service' => 'nullable|boolean',
            'description' => 'nullable|string|max:2000',
        ];
    }

    // ------------------------------------------------------------- AJAX для ТЧ документов

    /** GET /ajax/nomenclature?q= — список для автодополнения (с ценами). */
    public function search(): void
    {
        $this->authorize('catalogs.read');
        $q = Request::search();
        if (mb_strlen($q) < 1) {
            $this->json(['ok' => true, 'items' => []]);
        }
        $prices = new PriceRepository();
        $items = [];
        foreach ($this->nomRepo()->search($q, 20) as $row) {
            $items[] = [
                'id' => (int) $row['id'],
                'article' => $row['article'],
                'name' => $row['name'],
                'unit_name' => $row['unit_name'] ?? '',
                'vat_rate' => (float) $row['vat_rate'],
                'purchase_price' => (float) $row['purchase_price'],
                'retail_price' => $prices->currentPrice((int) $row['id'], 'retail') ?? (float) $row['purchase_price'],
            ];
        }
        $this->json(['ok' => true, 'items' => $items]);
    }

    /** GET /ajax/nomenclature/{id} — карточка для автозаполнения строки. */
    public function showAjax(string $id): void
    {
        $this->authorize('catalogs.read');
        $row = $this->nomRepo()->queryOne(
            'SELECT n.*, u.name AS unit_name FROM catalogs_nomenclature n
             LEFT JOIN catalogs_units u ON u.id = n.unit_id
             WHERE n.id = ? AND n.deleted_at IS NULL',
            [(int) $id]
        );
        if ($row === null) {
            $this->json(['ok' => false, 'message' => 'Не найдено'], 404);
        }
        $prices = new PriceRepository();
        $this->json([
            'ok' => true,
            'item' => [
                'id' => (int) $row['id'],
                'article' => $row['article'],
                'name' => $row['name'],
                'unit_name' => $row['unit_name'] ?? '',
                'vat_rate' => (float) $row['vat_rate'],
                'purchase_price' => (float) $row['purchase_price'],
                'retail_price' => $prices->currentPrice((int) $row['id'], 'retail') ?? (float) $row['purchase_price'],
            ],
        ]);
    }
}
