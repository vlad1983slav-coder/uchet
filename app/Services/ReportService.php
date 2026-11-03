<?php

declare(strict_types=1);

namespace Uchet\Services;

use Uchet\Repositories\MovementRepository;
use Uchet\Repositories\NomenclatureRepository;
use Uchet\Repositories\SettlementRepository;
use Uchet\Repositories\StockRepository;

/**
 * Данные для отчётов 1С:
 *  - остатки сейчас / на дату (пересчёт из registers_movements назад от текущего остатка);
 *  - движения по номенклатуре за период;
 *  - обороты по складу за период (начало / приход / расход / конец);
 *  - карточка товара;
 *  - взаиморасчёты.
 */
final class ReportService
{
    public function __construct(
        private readonly StockRepository $stock = new StockRepository(),
        private readonly MovementRepository $movements = new MovementRepository(),
        private readonly SettlementRepository $settlements = new SettlementRepository(),
        private readonly NomenclatureRepository $nom = new NomenclatureRepository(),
    ) {}

    /** Текущие остатки (registers_stock) с названиями. */
    public function stockNow(?int $warehouseId = null, ?string $q = null): array
    {
        return $this->stock->stockByWarehouse($warehouseId, $q);
    }

    /**
     * Остатки на дату (включительно): stock(date) = stock_now − in(после date) + out(после date).
     * Работает, потому что все изменения после начальных остатков проходят через registers_movements.
     *
     * @return array<int, array<string, mixed>>
     */
    public function stockAsOf(string $date, ?int $warehouseId = null, ?string $q = null): array
    {
        $rows = $this->stock->stockByWarehouse($warehouseId, $q);
        $after = $date . ' 23:59:59';
        $mv = $this->movements->query(
            'SELECT warehouse_id, nomenclature_id, party_id,
                    SUM(quantity_in) AS in_q, SUM(amount_in) AS in_a,
                    SUM(quantity_out) AS out_q, SUM(amount_out) AS out_a
             FROM registers_movements WHERE doc_date > ?
             GROUP BY warehouse_id, nomenclature_id, party_id',
            [$after]
        );
        $adj = [];
        foreach ($mv as $m) {
            $adj[$m['warehouse_id'] . ':' . $m['nomenclature_id'] . ':' . ($m['party_id'] ?? 'null')] = $m;
        }
        $out = [];
        foreach ($rows as $r) {
            $key = $r['warehouse_id'] . ':' . $r['nomenclature_id'] . ':' . ($r['party_id'] ?? 'null');
            $a = $adj[$key] ?? null;
            $qty = (float) $r['quantity'] - (float) ($a['in_q'] ?? 0) + (float) ($a['out_q'] ?? 0);
            if ($qty <= 0.0005) {
                continue;
            }
            $cost = (float) $r['cost'] - (float) ($a['in_a'] ?? 0) + (float) ($a['out_a'] ?? 0);
            $r['quantity'] = $qty;
            $r['cost'] = $cost;
            $r['unit_cost'] = $cost / $qty;
            $out[] = $r;
        }

        return $out;
    }

    /**
     * Движения за период с названиями.
     *
     * @param array{nomenclature_id?: int, warehouse_id?: int, date_from?: string, date_to?: string, document_type?: string} $f
     * @return array<int, array<string, mixed>>
     */
    public function movementsReport(array $f): array
    {
        $where = '1=1';
        $params = [];
        if (!empty($f['nomenclature_id'])) {
            $where .= ' AND m.nomenclature_id = ?';
            $params[] = (int) $f['nomenclature_id'];
        }
        if (!empty($f['warehouse_id'])) {
            $where .= ' AND m.warehouse_id = ?';
            $params[] = (int) $f['warehouse_id'];
        }
        if (!empty($f['document_type'])) {
            $where .= ' AND m.document_type = ?';
            $params[] = (string) $f['document_type'];
        }
        if (!empty($f['date_from'])) {
            $where .= ' AND m.doc_date >= ?';
            $params[] = $f['date_from'] . ' 00:00:00';
        }
        if (!empty($f['date_to'])) {
            $where .= ' AND m.doc_date <= ?';
            $params[] = $f['date_to'] . ' 23:59:59';
        }

        return $this->movements->query(
            'SELECT m.*, n.article, n.name AS nom_name, w.name AS warehouse_name, w.code AS warehouse_code
             FROM registers_movements m
             JOIN catalogs_nomenclature n ON n.id = m.nomenclature_id
             JOIN catalogs_warehouses w ON w.id = m.warehouse_id
             WHERE ' . $where . '
             ORDER BY m.doc_date, m.id',
            $params
        );
    }

    /**
     * Обороты по складу за период: начало, приход, расход, конец — по каждой номенклатуре.
     *
     * @return array<int, array<string, mixed>>
     */
    public function turnover(string $from, string $to, ?int $warehouseId = null): array
    {
        $fromDt = $from . ' 00:00:00';
        $toDt = $to . ' 23:59:59';
        $whSql = $warehouseId !== null ? ' AND warehouse_id = ?' : '';
        $whParams = $warehouseId !== null ? [$warehouseId] : [];

        // обороты за период
        $period = $this->movements->query(
            'SELECT warehouse_id, nomenclature_id,
                    SUM(quantity_in) AS in_q, SUM(amount_in) AS in_a,
                    SUM(quantity_out) AS out_q, SUM(amount_out) AS out_a
             FROM registers_movements
             WHERE doc_date BETWEEN ? AND ?' . $whSql . '
             GROUP BY warehouse_id, nomenclature_id',
            [$fromDt, $toDt, ...$whParams]
        );
        // текущие остатки
        $now = $this->stock->query(
            'SELECT warehouse_id, nomenclature_id, SUM(quantity) AS q, SUM(cost) AS c
             FROM registers_stock' . ($warehouseId !== null ? ' WHERE warehouse_id = ?' : '') . '
             GROUP BY warehouse_id, nomenclature_id',
            $whParams
        );
        // движения после конца и после кануна начала периода (для расчёта остатков на даты)
        $afterTo = $this->aggregateAfter($toDt, $warehouseId);
        $beforeFrom = $this->aggregateAfter(date('Y-m-d', strtotime($from . ' -1 day')) . ' 23:59:59', $warehouseId);

        $byPair = [];
        foreach ($now as $r) {
            $key = $r['warehouse_id'] . ':' . $r['nomenclature_id'];
            $byPair[$key] = [
                'warehouse_id' => (int) $r['warehouse_id'],
                'nomenclature_id' => (int) $r['nomenclature_id'],
                'now_q' => (float) $r['q'],
                'now_c' => (float) $r['c'],
            ];
        }
        foreach ($period as $r) {
            $key = $r['warehouse_id'] . ':' . $r['nomenclature_id'];
            $byPair[$key] ??= ['warehouse_id' => (int) $r['warehouse_id'], 'nomenclature_id' => (int) $r['nomenclature_id'], 'now_q' => 0.0, 'now_c' => 0.0];
            $byPair[$key]['in_q'] = (float) $r['in_q'];
            $byPair[$key]['in_a'] = (float) $r['in_a'];
            $byPair[$key]['out_q'] = (float) $r['out_q'];
            $byPair[$key]['out_a'] = (float) $r['out_a'];
        }

        $rows = [];
        foreach ($byPair as $key => $r) {
            $aTo = $afterTo[$key] ?? null;
            $aBefore = $beforeFrom[$key] ?? null;
            $endQ = $r['now_q'] - (float) ($aTo['in_q'] ?? 0) + (float) ($aTo['out_q'] ?? 0);
            $startQ = $r['now_q'] - (float) ($aBefore['in_q'] ?? 0) + (float) ($aBefore['out_q'] ?? 0);
            $endC = $r['now_c'] - (float) ($aTo['in_a'] ?? 0) + (float) ($aTo['out_a'] ?? 0);
            $startC = $r['now_c'] - (float) ($aBefore['in_a'] ?? 0) + (float) ($aBefore['out_a'] ?? 0);
            $nom = $this->nom->queryOne('SELECT article, name FROM catalogs_nomenclature WHERE id = ?', [$r['nomenclature_id']]);
            $rows[] = [
                'warehouse_id' => $r['warehouse_id'],
                'nomenclature_id' => $r['nomenclature_id'],
                'article' => $nom['article'] ?? '',
                'nom_name' => $nom['name'] ?? '',
                'start_q' => $startQ, 'start_c' => $startC,
                'in_q' => $r['in_q'] ?? 0.0, 'in_a' => $r['in_a'] ?? 0.0,
                'out_q' => $r['out_q'] ?? 0.0, 'out_a' => $r['out_a'] ?? 0.0,
                'end_q' => $endQ, 'end_c' => $endC,
            ];
        }
        usort($rows, static fn ($a, $b) => [$a['warehouse_id'], $a['article']] <=> [$b['warehouse_id'], $b['article']]);

        return $rows;
    }

    /** Движения после даты (агрегированные по паре склад+номенклатура) — для расчёта остатков на дату. */
    private function aggregateAfter(string $afterDt, ?int $warehouseId): array
    {
        $whSql = $warehouseId !== null ? ' AND warehouse_id = ?' : '';
        $rows = $this->movements->query(
            'SELECT warehouse_id, nomenclature_id,
                    SUM(quantity_in) AS in_q, SUM(amount_in) AS in_a,
                    SUM(quantity_out) AS out_q, SUM(amount_out) AS out_a
             FROM registers_movements WHERE doc_date > ?' . $whSql . '
             GROUP BY warehouse_id, nomenclature_id',
            [$afterDt, ...($warehouseId !== null ? [$warehouseId] : [])]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[$r['warehouse_id'] . ':' . $r['nomenclature_id']] = $r;
        }

        return $out;
    }

    /**
     * Карточка товара: остатки по складам/партиям + движения за период.
     *
     * @return array{nom: ?array, stock: array, movements: array}
     */
    public function card(int $nomenclatureId, string $from, string $to): array
    {
        $nom = $this->nom->queryOne(
            'SELECT n.*, u.name AS unit_name, c.name AS category_name
             FROM catalogs_nomenclature n
             LEFT JOIN catalogs_units u ON u.id = n.unit_id
             LEFT JOIN catalogs_categories c ON c.id = n.category_id
             WHERE n.id = ? AND n.deleted_at IS NULL',
            [$nomenclatureId]
        );
        $stock = $this->stock->query(
            'SELECT s.*, w.code AS warehouse_code, w.name AS warehouse_name, p.source_type, p.source_id
             FROM registers_stock s
             JOIN catalogs_warehouses w ON w.id = s.warehouse_id
             LEFT JOIN stock_parties p ON p.id = s.party_id
             WHERE s.nomenclature_id = ? AND s.quantity > 0
             ORDER BY w.code, s.party_id',
            [$nomenclatureId]
        );
        $movements = $this->movementsReport([
            'nomenclature_id' => $nomenclatureId,
            'date_from' => $from,
            'date_to' => $to,
        ]);

        return ['nom' => $nom, 'stock' => $stock, 'movements' => $movements];
    }

    /** Взаиморасчёты: баланс по каждому контрагенту (>0 — нам должны, <0 — мы должны). */
    public function settlements(): array
    {
        return $this->settlements->query(
            'SELECT c.id, c.inn, c.kpp, c.name, c.type, COALESCE(SUM(s.amount), 0) AS balance
             FROM catalogs_counterparties c
             LEFT JOIN registers_settlements s ON s.counterparty_id = c.id
             WHERE c.deleted_at IS NULL
             GROUP BY c.id, c.inn, c.kpp, c.name, c.type
             ORDER BY balance DESC'
        );
    }

    /** История взаиморасчётов по контрагенту. */
    public function settlementHistory(int $counterpartyId): array
    {
        return $this->settlements->byCounterparty($counterpartyId);
    }
}
