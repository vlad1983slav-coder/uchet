<?php

declare(strict_types=1);

namespace Uchet\Controllers;

use Uchet\Services\DocumentService;

/** Расходные накладные (РН): отгрузка покупателю, цена розничная, списание FIFO. */
final class IssueController extends BaseDocumentController
{
    protected function docType(): string
    {
        return DocumentService::TYPE_ISSUES;
    }

    protected function titleSingular(): string
    {
        return 'Расходная накладная';
    }

    protected function titlePlural(): string
    {
        return 'Расходные накладные (РН)';
    }

    protected function routeBase(): string
    {
        return 'issues';
    }

    protected function priceMode(): string
    {
        return 'retail';
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
