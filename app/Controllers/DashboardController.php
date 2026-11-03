<?php

declare(strict_types=1);

namespace Uchet\Controllers;

use Uchet\Core\Controller;
use Uchet\Repositories\MovementRepository;
use Uchet\Repositories\SettlementRepository;
use Uchet\Repositories\StockRepository;
use Uchet\Services\DocumentService;

/** Главная панель: ключевые показатели складского учёта. */
final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->authorize('documents.read');
        $stock = new StockRepository();
        $movements = new MovementRepository();
        $settlements = new SettlementRepository();

        $totals = $stock->queryOne('SELECT COUNT(*) AS positions, COALESCE(SUM(quantity),0) AS qty, COALESCE(SUM(cost),0) AS cost FROM registers_stock WHERE quantity > 0');
        $byWarehouse = $stock->query(
            'SELECT w.code, w.name, COUNT(*) AS positions, COALESCE(SUM(s.quantity),0) AS qty, COALESCE(SUM(s.cost),0) AS cost
             FROM registers_stock s JOIN catalogs_warehouses w ON w.id = s.warehouse_id
             WHERE s.quantity > 0 AND w.deleted_at IS NULL
             GROUP BY w.id, w.code, w.name ORDER BY w.code'
        );
        // товары ниже минимального остатка
        $lowStock = $stock->query(
            'SELECT n.id, n.article, n.name, n.min_stock, COALESCE(SUM(s.quantity), 0) AS qty
             FROM catalogs_nomenclature n
             LEFT JOIN registers_stock s ON s.nomenclature_id = n.id
             WHERE n.deleted_at IS NULL AND n.is_service = 0
             GROUP BY n.id, n.article, n.name, n.min_stock
             HAVING qty < n.min_stock
             ORDER BY n.article LIMIT 20'
        );
        // документы сегодня по типам
        $docsToday = [];
        $service = DocumentService::make();
        foreach (DocumentService::TYPES as $type) {
            $repo = $service->repoFor($type);
            $row = $repo->queryOne(
                'SELECT COUNT(*) AS total,
                        SUM(status = "posted") AS posted,
                        SUM(status = "draft") AS draft
                 FROM ' . $repo->tableName() . ' WHERE deleted_at IS NULL AND DATE(doc_date) = CURDATE()'
            );
            $docsToday[$type] = $row ?: ['total' => 0, 'posted' => 0, 'draft' => 0];
        }
        // долги
        $debt = $settlements->queryOne(
            'SELECT COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END),0) AS recv,
                    COALESCE(SUM(CASE WHEN amount < 0 THEN -amount ELSE 0 END),0) AS pay
             FROM registers_settlements'
        );
        // последние движения (лента активности)
        $recent = $movements->query(
            'SELECT m.*, n.article, n.name AS nom_name, w.code AS warehouse_code
             FROM registers_movements m
             JOIN catalogs_nomenclature n ON n.id = m.nomenclature_id
             JOIN catalogs_warehouses w ON w.id = m.warehouse_id
             ORDER BY m.id DESC LIMIT 10'
        );

        $this->render('dashboard.index', [
            'title' => 'Главная',
            'totals' => $totals,
            'byWarehouse' => $byWarehouse,
            'lowStock' => $lowStock,
            'docsToday' => $docsToday,
            'debt' => $debt,
            'recent' => $recent,
        ]);
    }
}
