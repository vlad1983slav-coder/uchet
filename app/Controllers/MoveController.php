<?php

declare(strict_types=1);

namespace Uchet\Controllers;

use Uchet\Services\DocumentService;

/** Перемещения между складами (ПМ): склад-источник → склад-получатель, партия сохраняется. */
final class MoveController extends BaseDocumentController
{
    protected function docType(): string
    {
        return DocumentService::TYPE_MOVES;
    }

    protected function titleSingular(): string
    {
        return 'Перемещение между складами';
    }

    protected function titlePlural(): string
    {
        return 'Перемещения между складами (ПМ)';
    }

    protected function routeBase(): string
    {
        return 'moves';
    }

    protected function priceMode(): string
    {
        return 'cost';
    }

    protected function extraHeaderRules(): array
    {
        return [
            'warehouse_from_id' => 'required|integer|exists:catalogs_warehouses,id',
            'warehouse_to_id' => 'required|integer|exists:catalogs_warehouses,id',
        ];
    }

    protected function extraHeaderData(array $v): array
    {
        return [
            'warehouse_from_id' => (int) $v['warehouse_from_id'],
            'warehouse_to_id' => (int) $v['warehouse_to_id'],
        ];
    }
}
