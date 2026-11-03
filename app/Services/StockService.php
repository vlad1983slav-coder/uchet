<?php

declare(strict_types=1);

namespace Uchet\Services;

use Uchet\Core\HttpException;
use Uchet\Repositories\MovementRepository;
use Uchet\Repositories\StockRepository;

/**
 * Партионный учёт (регистр остатков registers_stock + движения).
 *
 * - FIFO: списание по партиям в порядке их создания (ORDER BY party_id), себестоимость партии;
 * - AVG: списание по средней себестоимости остатка (метод из настроек stock_method);
 * - отрицательные остатки блокируются на уровне транзакции (строки блокируются FOR UPDATE,
 *   недостаток → исключение 422), если в настройках negative_stock=forbid;
 * - каждая операция пишет registers_movements.
 */
final class StockService
{
    private const EPS = 0.0005; // допуск DECIMAL(15,3)

    public function __construct(
        private readonly StockRepository $stock = new StockRepository(),
        private readonly MovementRepository $movements = new MovementRepository(),
    ) {}

    public function stock(): StockRepository
    {
        return $this->stock;
    }

    public function movements(): MovementRepository
    {
        return $this->movements;
    }

    /**
     * Приход на склад: остаток партии + движение (quantity_in).
     */
    public function receive(
        int $warehouseId,
        int $nomenclatureId,
        int $partyId,
        float $qty,
        float $totalCost,
        string $documentType,
        int $documentId,
        string $docDate,
    ): void {
        $this->stock->addQty($warehouseId, $nomenclatureId, $partyId, $qty, $totalCost);
        $this->movements->add($documentType, $documentId, $docDate, $warehouseId, $nomenclatureId, $partyId, $qty, 0.0, $totalCost, 0.0);
    }

    /**
     * Списание со склада. Возвращает массив списаний по партиям:
     * [['party_id' => int, 'qty' => float, 'amount' => float], ...]
     *
     * @throws HttpException(422) если недостаточно остатка и отрицательные запрещены
     */
    public function writeOff(
        int $warehouseId,
        int $nomenclatureId,
        float $qty,
        string $documentType,
        int $documentId,
        string $docDate,
        bool $checkNegative = true,
    ): array {
        if ($qty <= self::EPS) {
            throw new HttpException(422, 'Количество списания должно быть положительным');
        }

        // блокируем строки остатка на время транзакции — защита от гонки списаний
        $parties = $this->stock->partiesForUpdate($warehouseId, $nomenclatureId);
        $available = array_sum(array_map(static fn (array $p) => (float) $p['quantity'], $parties));

        if ($checkNegative && !SettingsService::negativeStockAllowed() && $available + self::EPS < $qty) {
            $name = $this->nomenclatureName($nomenclatureId);
            throw new HttpException(422, sprintf(
                'Недостаточно товара «%s» на складе: требуется %s, в наличии %s',
                $name,
                number_format($qty, 3, '.', ' '),
                number_format($available, 3, '.', ' ')
            ));
        }

        $method = SettingsService::stockMethod();
        $allocations = [];
        $remaining = $qty;

        if ($method === 'AVG') {
            $totals = $this->stock->totalQtyAndCost($warehouseId, $nomenclatureId);
            $avgCost = $totals['q'] > self::EPS ? $totals['c'] / $totals['q'] : 0.0;
        }

        foreach ($parties as $party) {
            if ($remaining <= self::EPS) {
                break;
            }
            $partyQty = (float) $party['quantity'];
            $take = min($remaining, $partyQty);
            if ($method === 'AVG') {
                $amount = round($take * $avgCost, 2);
            } else { // FIFO — себестоимость именно этой партии
                $unitCost = $partyQty > self::EPS ? (float) $party['cost'] / $partyQty : 0.0;
                $amount = round($take * $unitCost, 2);
            }

            $this->stock->subtractFromRow((int) $party['id'], $take, $amount);
            $this->movements->add(
                $documentType, $documentId, $docDate, $warehouseId, $nomenclatureId,
                (int) $party['party_id'], 0.0, $take, 0.0, $amount
            );
            $allocations[] = ['party_id' => (int) $party['party_id'], 'qty' => $take, 'amount' => $amount];
            $remaining -= $take;
        }

        // отрицательный остаток разрешён — остаток списания просто не покрывается
        return $allocations;
    }

    /** Создать партию и вернуть её id. */
    public function createParty(int $nomenclatureId, string $sourceType, ?int $sourceId): int
    {
        return $this->stock->createParty($nomenclatureId, $sourceType, $sourceId);
    }

    public function totalQty(int $warehouseId, int $nomenclatureId): float
    {
        return $this->stock->totalQty($warehouseId, $nomenclatureId);
    }

    /** Средняя себестоимость единицы остатка (для оприходования излишков при инвентаризации). */
    public function avgCost(int $warehouseId, int $nomenclatureId): float
    {
        $totals = $this->stock->totalQtyAndCost($warehouseId, $nomenclatureId);

        return $totals['q'] > self::EPS ? $totals['c'] / $totals['q'] : 0.0;
    }

    private function nomenclatureName(int $nomenclatureId): string
    {
        $row = $this->stock->queryOne(
            'SELECT article, name FROM catalogs_nomenclature WHERE id = ?',
            [$nomenclatureId]
        );

        return $row === null ? '#' . $nomenclatureId : trim(($row['article'] ?? '') . ' ' . ($row['name'] ?? ''));
    }
}
