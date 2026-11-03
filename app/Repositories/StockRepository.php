<?php

declare(strict_types=1);

namespace Uchet\Repositories;

use Uchet\Core\Database;
use Uchet\Core\Repository;
use Uchet\Models\Stock;

/**
 * Регистр остатков registers_stock + таблица партий stock_parties.
 * Все операции — внутри транзакции сервиса; чтение для списания — с FOR UPDATE.
 */
final class StockRepository extends Repository
{
    private const EPS = 0.0005; // допуск для DECIMAL(15,3)

    public function __construct()
    {
        parent::__construct('registers_stock', Stock::class, false);
    }

    /**
     * Партии остатка по складу+номенклатуре в порядке FIFO (по возрастанию party_id),
     * с блокировкой строк (вызывать внутри транзакции).
     *
     * @return array<int, array<string, mixed>>
     */
    public function partiesForUpdate(int $warehouseId, int $nomenclatureId): array
    {
        return $this->query(
            'SELECT s.id, s.party_id, s.quantity, s.cost
             FROM registers_stock s
             WHERE s.warehouse_id = ? AND s.nomenclature_id = ? AND s.quantity > ?
             ORDER BY s.party_id ASC
             FOR UPDATE',
            [$warehouseId, $nomenclatureId, self::EPS]
        );
    }

    /** Блокировка всех строк остатка по паре склад+номенклатура (для отмены движений). */
    public function lockRows(int $warehouseId, int $nomenclatureId): array
    {
        return $this->query(
            'SELECT id, party_id, quantity, cost FROM registers_stock
             WHERE warehouse_id = ? AND nomenclature_id = ? FOR UPDATE',
            [$warehouseId, $nomenclatureId]
        );
    }

    /** Увеличить остаток партии (upsert по уникальному ключу склад+номенклатура+партия). */
    public function addQty(int $warehouseId, int $nomenclatureId, int $partyId, float $qty, float $cost): void
    {
        $this->execute(
            'INSERT INTO registers_stock (warehouse_id, nomenclature_id, party_id, quantity, cost, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), cost = cost + VALUES(cost), updated_at = NOW()',
            [$warehouseId, $nomenclatureId, $partyId, $qty, $cost]
        );
    }

    /** Уменьшить остаток конкретной строки; если остаток исчерпан — удалить строку. */
    public function subtractFromRow(int $rowId, float $qty, float $cost): void
    {
        $this->execute(
            'UPDATE registers_stock SET quantity = quantity - ?, cost = cost - ?, updated_at = NOW() WHERE id = ?',
            [$qty, $cost, $rowId]
        );
        $this->execute('DELETE FROM registers_stock WHERE id = ? AND quantity <= ?', [$rowId, self::EPS]);
    }

    /** Уменьшить остаток партии (строка подбирается по складу+номенклатуре+партии). */
    public function subtractQty(int $warehouseId, int $nomenclatureId, ?int $partyId, float $qty, float $cost): void
    {
        if ($partyId !== null) {
            $row = $this->queryOne(
                'SELECT id FROM registers_stock WHERE warehouse_id = ? AND nomenclature_id = ? AND party_id = ? FOR UPDATE',
                [$warehouseId, $nomenclatureId, $partyId]
            );
            if ($row !== null) {
                $this->subtractFromRow((int) $row['id'], $qty, $cost);

                return;
            }
            // строки нет — создадим с отрицательным? нет: при отмене прихода партии могут не быть — тогда ничего
            return;
        }
        // партия не задана (средняя себестоимость): списываем с первой партии FIFO
        $rows = $this->partiesForUpdate($warehouseId, $nomenclatureId);
        $remaining = $qty;
        $remainingCost = $cost;
        foreach ($rows as $row) {
            if ($remaining <= self::EPS) {
                break;
            }
            $take = min($remaining, (float) $row['quantity']);
            $unit = (float) $row['quantity'] > 0 ? (float) $row['cost'] / (float) $row['quantity'] : 0;
            $amount = round($take * $unit, 2);
            $this->subtractFromRow((int) $row['id'], $take, $amount);
            $remaining -= $take;
            $remainingCost -= $amount;
        }
    }

    public function totalQty(int $warehouseId, int $nomenclatureId): float
    {
        $row = $this->queryOne(
            'SELECT COALESCE(SUM(quantity), 0) AS q, COALESCE(SUM(cost), 0) AS c
             FROM registers_stock WHERE warehouse_id = ? AND nomenclature_id = ?',
            [$warehouseId, $nomenclatureId]
        );

        return (float) ($row['q'] ?? 0);
    }

    /** @return array{q: float, c: float} — суммарное количество и себестоимость остатка */
    public function totalQtyAndCost(int $warehouseId, int $nomenclatureId): array
    {
        $row = $this->queryOne(
            'SELECT COALESCE(SUM(quantity), 0) AS q, COALESCE(SUM(cost), 0) AS c
             FROM registers_stock WHERE warehouse_id = ? AND nomenclature_id = ? AND quantity > ?',
            [$warehouseId, $nomenclatureId, self::EPS]
        );

        return ['q' => (float) ($row['q'] ?? 0), 'c' => (float) ($row['c'] ?? 0)];
    }

    // --- партии ---

    public function createParty(int $nomenclatureId, string $sourceType, ?int $sourceId): int
    {
        $this->execute(
            'INSERT INTO stock_parties (nomenclature_id, source_type, source_id) VALUES (?, ?, ?)',
            [$nomenclatureId, $sourceType, $sourceId]
        );

        return (int) Database::lastInsertId();
    }

    public function party(int $partyId): ?array
    {
        return $this->queryOne('SELECT * FROM stock_parties WHERE id = ?', [$partyId]);
    }

    /** Была ли партия создана данным документом (нужно для корректной отмены проведения). */
    public function partyCreatedBy(int $partyId, string $sourceType, int $sourceId): bool
    {
        $row = $this->queryOne(
            'SELECT id FROM stock_parties WHERE id = ? AND source_type = ? AND source_id = ?',
            [$partyId, $sourceType, $sourceId]
        );

        return $row !== null;
    }

    /** Удалить партию вместе со строками остатка (при отмене проведения создавшего её документа). */
    public function deleteParty(int $partyId): void
    {
        $this->execute('DELETE FROM registers_stock WHERE party_id = ?', [$partyId]);
        $this->execute('DELETE FROM stock_parties WHERE id = ?', [$partyId]);
    }

    /** Остатки по складу (для отчётов): с названиями номенклатуры. */
    public function stockByWarehouse(?int $warehouseId = null, ?string $search = null): array
    {
        $where = 's.quantity > 0';
        $params = [];
        if ($warehouseId !== null) {
            $where .= ' AND s.warehouse_id = ?';
            $params[] = $warehouseId;
        }
        if ($search !== null && $search !== '') {
            $where .= ' AND (n.article LIKE ? OR n.name LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        return $this->query(
            'SELECT s.warehouse_id, w.code AS warehouse_code, w.name AS warehouse_name,
                    s.nomenclature_id, n.article, n.name, n.unit_id, u.name AS unit_name,
                    s.party_id, p.source_type, p.source_id,
                    s.quantity, s.cost, s.updated_at
             FROM registers_stock s
             JOIN catalogs_nomenclature n ON n.id = s.nomenclature_id AND n.deleted_at IS NULL
             JOIN catalogs_warehouses w ON w.id = s.warehouse_id AND w.deleted_at IS NULL
             LEFT JOIN catalogs_units u ON u.id = n.unit_id
             LEFT JOIN stock_parties p ON p.id = s.party_id
             WHERE ' . $where . '
             ORDER BY w.code, n.article, s.party_id',
            $params
        );
    }
}
