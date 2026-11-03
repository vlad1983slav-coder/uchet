<?php

declare(strict_types=1);

namespace Uchet\Controllers;

use Uchet\Core\Auth;
use Uchet\Core\Repository;
use Uchet\Core\Request;
use Uchet\Repositories\CounterpartyRepository;

/** Справочник «Контрагенты» + AJAX-поиск (по ИНН/наименованию, с фильтром по типу). */
final class CounterpartyController extends CatalogController
{
    private function cpRepo(): CounterpartyRepository
    {
        return new CounterpartyRepository();
    }

    protected function repo(): Repository
    {
        return $this->cpRepo();
    }

    protected function titlePlural(): string
    {
        return 'Контрагенты';
    }

    protected function routeBase(): string
    {
        return 'catalogs.counterparties';
    }

    protected function searchColumn(): string
    {
        return 'name';
    }

    protected function columns(): array
    {
        return [
            ['key' => 'inn', 'label' => 'ИНН', 'sortable' => true],
            ['key' => 'kpp', 'label' => 'КПП'],
            ['key' => 'name', 'label' => 'Наименование', 'sortable' => true],
            ['key' => 'type', 'label' => 'Тип'],
            ['key' => 'phone', 'label' => 'Телефон'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'inn', 'label' => 'ИНН', 'type' => 'text', 'maxlength' => 12],
            ['name' => 'kpp', 'label' => 'КПП', 'type' => 'text', 'maxlength' => 9],
            ['name' => 'name', 'label' => 'Наименование', 'type' => 'text', 'required' => true, 'maxlength' => 500],
            ['name' => 'type', 'label' => 'Тип', 'type' => 'select', 'required' => true,
                'options' => ['supplier' => 'Поставщик', 'customer' => 'Покупатель', 'both' => 'Поставщик и покупатель']],
            ['name' => 'phone', 'label' => 'Телефон', 'type' => 'text', 'maxlength' => 64],
            ['name' => 'email', 'label' => 'E-mail', 'type' => 'email', 'maxlength' => 255],
            ['name' => 'address', 'label' => 'Адрес', 'type' => 'text', 'maxlength' => 500],
        ];
    }

    protected function rules(?int $excludeId = null): array
    {
        return [
            'inn' => 'nullable|string|max:12',
            'kpp' => 'nullable|string|max:9',
            'name' => 'required|string|max:500',
            'type' => 'required|in:supplier,customer,both',
            'phone' => 'nullable|string|max:64',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
        ];
    }

    /** GET /ajax/counterparties?q=&type=supplier|customer */
    public function search(): void
    {
        $this->authorize('catalogs.read');
        $q = Request::search();
        $type = Request::get('type');
        if (mb_strlen($q) < 1) {
            $this->json(['ok' => true, 'items' => []]);
        }
        $items = array_map(static fn (array $row) => [
            'id' => (int) $row['id'],
            'inn' => $row['inn'],
            'kpp' => $row['kpp'],
            'name' => $row['name'],
            'type' => $row['type'],
        ], $this->cpRepo()->search($q, in_array($type, ['supplier', 'customer'], true) ? (string) $type : null, 20));

        $this->json(['ok' => true, 'items' => $items]);
    }
}
