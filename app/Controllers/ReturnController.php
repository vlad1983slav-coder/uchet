<?php

declare(strict_types=1);

namespace Uchet\Controllers;

use Uchet\Core\Request;
use Uchet\Services\DocumentService;

/** Возвраты: ВП — поставщику (списание, цена закупочная), ВК — от покупателя (приход, цена розничная). */
final class ReturnController extends BaseDocumentController
{
    protected function docType(): string
    {
        return DocumentService::TYPE_RETURNS;
    }

    protected function titleSingular(): string
    {
        return 'Возврат';
    }

    protected function titlePlural(): string
    {
        return 'Возвраты (ВП / ВК)';
    }

    protected function routeBase(): string
    {
        return 'returns';
    }

    protected function extraHeaderRules(): array
    {
        return [
            'direction' => 'required|in:to_supplier,from_customer',
            'warehouse_id' => 'required|integer|exists:catalogs_warehouses,id',
            'counterparty_id' => 'required|integer|exists:catalogs_counterparties,id',
        ];
    }

    protected function extraHeaderData(array $v): array
    {
        return [
            'direction' => (string) $v['direction'],
            'warehouse_id' => (int) $v['warehouse_id'],
            'counterparty_id' => (int) $v['counterparty_id'],
        ];
    }

    /** Цена по умолчанию зависит от направления: поставщику — закупочная, от покупателя — розничная. */
    protected function priceMode(): string
    {
        $direction = (string) (Request::post('direction') ?: Request::get('direction') ?: '');

        return $direction === 'from_customer' ? 'retail' : 'purchase';
    }
}
