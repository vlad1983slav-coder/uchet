<?php

declare(strict_types=1);

namespace Uchet\Controllers;

use Uchet\Core\Controller;
use Uchet\Core\NotFoundException;
use Uchet\Core\Request;
use Uchet\Repositories\CounterpartyRepository;
use Uchet\Repositories\NomenclatureRepository;
use Uchet\Repositories\WarehouseRepository;
use Uchet\Services\ReportService;

/** Отчёты в стиле 1С: остатки на дату, движения по номенклатуре, обороты по складу, карточка товара, взаиморасчёты. */
final class ReportController extends Controller
{
    private function reports(): ReportService
    {
        static $service = null;

        return $service ??= new ReportService();
    }

    private function defaultPeriod(): array
    {
        return ['from' => date('Y-m-01'), 'to' => date('Y-m-d')];
    }

    /** Остатки на дату (по умолчанию — сегодня, т.е. текущие). */
    public function stock(): void
    {
        $this->authorize('reports.read');
        $date = (string) (Request::get('date') ?: date('Y-m-d'));
        $warehouseId = Request::get('warehouse_id') ? (int) Request::get('warehouse_id') : null;
        $q = Request::search() ?: null;

        $rows = $date === date('Y-m-d')
            ? $this->reports()->stockNow($warehouseId, $q)
            : $this->reports()->stockAsOf($date, $warehouseId, $q);

        $totalQty = array_sum(array_map(static fn ($r) => (float) $r['quantity'], $rows));
        $totalCost = array_sum(array_map(static fn ($r) => (float) $r['cost'], $rows));

        $this->render('reports.stock', [
            'title' => 'Остатки товаров' . ($date === date('Y-m-d') ? ' (текущие)' : ' на ' . d($date)),
            'rows' => $rows,
            'date' => $date,
            'warehouseId' => $warehouseId,
            'q' => $q ?? '',
            'warehouses' => array_map(fn ($m) => $m->toArray(), (new WarehouseRepository())->all([], 'code')),
            'totalQty' => $totalQty,
            'totalCost' => $totalCost,
        ]);
    }

    /** Движения по номенклатуре за период. */
    public function movements(): void
    {
        $this->authorize('reports.read');
        $period = $this->defaultPeriod();
        $f = [
            'nomenclature_id' => Request::get('nomenclature_id') ? (int) Request::get('nomenclature_id') : null,
            'warehouse_id' => Request::get('warehouse_id') ? (int) Request::get('warehouse_id') : null,
            'document_type' => Request::get('document_type') ?: null,
            'date_from' => Request::get('date_from') ?: $period['from'],
            'date_to' => Request::get('date_to') ?: $period['to'],
        ];
        $rows = $this->reports()->movementsReport($f);

        $this->render('reports.movements', [
            'title' => 'Движения товаров за период',
            'rows' => $rows,
            'f' => $f,
            'warehouses' => array_map(fn ($m) => $m->toArray(), (new WarehouseRepository())->all([], 'code')),
            'nomenclatures' => (new NomenclatureRepository())->all([], 'article'),
            'types' => [
                'receipts' => 'ПН (приход)', 'issues' => 'РН (расход)', 'moves' => 'ПМ (перемещение)',
                'inventories' => 'ИНВ (инвентаризация)', 'returns' => 'ВП/ВК (возвраты)',
            ],
        ]);
    }

    /** Обороты по складу за период: начало / приход / расход / конец. */
    public function turnover(): void
    {
        $this->authorize('reports.read');
        $period = $this->defaultPeriod();
        $from = (string) (Request::get('date_from') ?: $period['from']);
        $to = (string) (Request::get('date_to') ?: $period['to']);
        $warehouseId = Request::get('warehouse_id') ? (int) Request::get('warehouse_id') : null;

        $rows = $this->reports()->turnover($from, $to, $warehouseId);

        $this->render('reports.turnover', [
            'title' => 'Обороты по складам с ' . d($from) . ' по ' . d($to),
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
            'warehouseId' => $warehouseId,
            'warehouses' => array_map(fn ($m) => $m->toArray(), (new WarehouseRepository())->all([], 'code')),
        ]);
    }

    /** Карточка товара: остатки по складам и движения. */
    public function card(string $id): void
    {
        $this->authorize('reports.read');
        $period = $this->defaultPeriod();
        $from = (string) (Request::get('date_from') ?: $period['from']);
        $to = (string) (Request::get('date_to') ?: $period['to']);
        $card = $this->reports()->card((int) $id, $from, $to);
        if ($card['nom'] === null) {
            throw new NotFoundException('Номенклатура не найдена');
        }
        $this->render('reports.card', [
            'title' => 'Карточка товара: ' . $card['nom']['article'],
            'nom' => $card['nom'],
            'stock' => $card['stock'],
            'movements' => $card['movements'],
            'from' => $from,
            'to' => $to,
        ]);
    }

    /** Взаиморасчёты с контрагентами. */
    public function settlements(): void
    {
        $this->authorize('reports.read');
        $cpId = Request::get('counterparty_id') ? (int) Request::get('counterparty_id') : null;
        $this->render('reports.settlements', [
            'title' => 'Взаиморасчёты с контрагентами',
            'rows' => $this->reports()->settlements(),
            'history' => $cpId !== null ? $this->reports()->settlementHistory($cpId) : null,
            'cpId' => $cpId,
            'counterparties' => array_map(fn ($m) => $m->toArray(), (new CounterpartyRepository())->all([], 'name')),
        ]);
    }
}
