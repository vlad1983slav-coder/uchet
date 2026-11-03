<?php

declare(strict_types=1);

namespace Uchet\Controllers;

use Uchet\Core\HttpException;
use Uchet\Core\Request;
use Uchet\Core\Session;
use Uchet\Repositories\StockRepository;
use Uchet\Services\AuditService;
use Uchet\Services\DocumentService;

/**
 * Инвентаризации (ИНВ): при создании строки подставляются учётные остатки склада;
 * факт вводится вручную; проведение формирует излишки/недостачи.
 */
final class InventoryController extends BaseDocumentController
{
    protected function docType(): string
    {
        return DocumentService::TYPE_INVENTORIES;
    }

    protected function titleSingular(): string
    {
        return 'Инвентаризация';
    }

    protected function titlePlural(): string
    {
        return 'Инвентаризации (ИНВ)';
    }

    protected function routeBase(): string
    {
        return 'inventories';
    }

    protected function extraHeaderRules(): array
    {
        return [
            'warehouse_id' => 'required|integer|exists:catalogs_warehouses,id',
        ];
    }

    protected function extraHeaderData(array $v): array
    {
        return ['warehouse_id' => (int) $v['warehouse_id']];
    }

    protected function lineRules(): array
    {
        return [
            'nomenclature_id' => 'required|integer|exists:catalogs_nomenclature,id',
            'book_quantity' => 'nullable|numeric|min:0',
            'fact_quantity' => 'required|numeric|min:0',
            'party_id' => 'nullable|integer',
        ];
    }

    /** Форма создания: подставляем учётные остатки выбранного склада. */
    public function create(): void
    {
        $this->authorize('documents.create');
        $whId = (int) (Request::get('warehouse_id') ?: 0);
        $form = $this->formData(null);
        $form['header']['warehouse_id'] = $whId ?: null;
        $form['lines'] = $whId > 0 ? $this->bookLines($whId) : [];
        $this->render('documents.form', $form);
    }

    /** POST: обновить учётные количества черновика по текущим остаткам склада. */
    public function refill(string $id): void
    {
        $this->authorize('documents.update');
        $this->csrf();
        $repo = $this->repo();
        $header = $repo->find((int) $id);
        if ($header === null) {
            throw new \Uchet\Core\NotFoundException('Документ не найден');
        }
        if ($header->status === 'posted') {
            throw new HttpException(422, 'Нельзя обновить остатки у проведённого документа');
        }
        $repo->replaceLines((int) $id, $this->bookLines((int) $header->warehouse_id));
        AuditService::log($repo->tableName(), (int) $id, 'update', null, ['refill' => 'book quantities from stock']);
        Session::flash('success', 'Учётные количества обновлены по остаткам склада');
        $this->redirect(url($this->routeBase() . '.edit', ['id' => $id]));
    }

    /** Строки «учёт = факт = текущий остаток» по складу, сгруппированные по номенклатуре. */
    private function bookLines(int $warehouseId): array
    {
        $stock = (new StockRepository())->stockByWarehouse($warehouseId);
        $byNom = [];
        foreach ($stock as $row) {
            $nomId = (int) $row['nomenclature_id'];
            $byNom[$nomId]['qty'] = ($byNom[$nomId]['qty'] ?? 0) + (float) $row['quantity'];
            $byNom[$nomId]['article'] = $row['article'];
            $byNom[$nomId]['nom_name'] = $row['name'];
            $byNom[$nomId]['unit_name'] = $row['unit_name'] ?? '';
        }
        $lines = [];
        foreach ($byNom as $nomId => $agg) {
            $lines[] = [
                'nomenclature_id' => $nomId,
                'book_quantity' => round($agg['qty'], 3),
                'fact_quantity' => round($agg['qty'], 3),
                'party_id' => null,
                'price' => 0,
                'vat_rate' => 20,
                'article' => $agg['article'],
                'nom_name' => $agg['nom_name'],
                'unit_name' => $agg['unit_name'],
            ];
        }

        return $lines;
    }
}
