<?php

declare(strict_types=1);

namespace Uchet\Controllers;

use Uchet\Services\DocumentService;

/** Приходные накладные (ПН): приход от поставщика, цена закупочная. */
final class ReceiptController extends BaseDocumentController
{
    protected function docType(): string
    {
        return DocumentService::TYPE_RECEIPTS;
    }

    protected function titleSingular(): string
    {
        return 'Приходная накладная';
    }

    protected function titlePlural(): string
    {
        return 'Приходные накладные (ПН)';
    }

    protected function routeBase(): string
    {
        return 'receipts';
    }

    protected function priceMode(): string
    {
        return 'purchase';
    }

    protected function extraHeaderRules(): array
    {
        return [
            'warehouse_id' => 'required|integer|exists:catalogs_warehouses,id',
            'counterparty_id' => 'required|integer|exists:catalogs_counterparties,id',
        ];
    }

    protected function extraHeaderData(array $v): array
    {
        return [
            'warehouse_id' => (int) $v['warehouse_id'],
            'counterparty_id' => (int) $v['counterparty_id'],
        ];
    }
}
