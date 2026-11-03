<?php

declare(strict_types=1);

namespace Uchet\Repositories;

use Uchet\Core\Repository;
use Uchet\Models\Price;

/** Регистр цен registers_prices (закупочная / розничная). */
final class PriceRepository extends Repository
{
    public function __construct()
    {
        parent::__construct('registers_prices', Price::class);
    }

    /** Актуальная цена на дату: последняя date_from <= $date. */
    public function currentPrice(int $nomenclatureId, string $priceType, ?string $date = null): ?float
    {
        $date ??= date('Y-m-d H:i:s');
        $row = $this->queryOne(
            'SELECT price FROM registers_prices
             WHERE nomenclature_id = ? AND price_type = ? AND deleted_at IS NULL AND date_from <= ?
             ORDER BY date_from DESC LIMIT 1',
            [$nomenclatureId, $priceType, $date]
        );

        return $row === null ? null : (float) $row['price'];
    }

    public function setPrice(int $nomenclatureId, string $priceType, float $price, ?string $dateFrom = null): void
    {
        $this->create([
            'nomenclature_id' => $nomenclatureId,
            'price_type' => $priceType,
            'price' => $price,
            'date_from' => $dateFrom ?? date('Y-m-d H:i:s'),
        ]);
    }
}
