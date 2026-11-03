<?php
/** Остатки товаров (сейчас или на дату).
 * @var string $title @var array $rows @var string $date @var int|null $warehouseId @var string $q
 * @var array $warehouses @var float $totalQty @var float $totalCost
 */
?>
<div class="card-1c">
    <div class="card-1c-header"><span><?= e($title) ?></span></div>
    <form method="get" class="filters-bar">
        <label class="d-flex align-items-center gap-1 small">На дату:
            <input type="date" name="date" value="<?= e($date) ?>" class="form-control form-control-sm"></label>
        <select name="warehouse_id" class="form-select form-select-sm" style="width:200px">
            <option value="">Все склады</option>
            <?php foreach ($warehouses as $w): ?>
                <option value="<?= (int) $w['id'] ?>" <?= $warehouseId === (int) $w['id'] ? 'selected' : '' ?>><?= e($w['code'] . ' ' . $w['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="search" name="q" value="<?= e($q) ?>" class="form-control form-control-sm" placeholder="артикул / название..." style="width:200px">
        <button class="btn btn-sm btn-outline-primary" type="submit">Показать</button>
    </form>
    <div class="table-responsive">
        <table class="table table-sm table-1c mb-0">
            <thead><tr>
                <th>Склад</th><th>Артикул</th><th>Номенклатура</th><th>Партия</th>
                <th class="text-end">Количество</th><th class="text-end">Себестоимость</th><th class="text-end">Цена ед.</th><th>Ед.изм.</th>
            </tr></thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Остатков нет</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['warehouse_code'] . ' ' . $r['warehouse_name']) ?></td>
                    <td><a href="<?= e(url('reports.card', ['id' => $r['nomenclature_id']])) ?>"><?= e($r['article']) ?></a></td>
                    <td><?= e($r['name']) ?></td>
                    <td class="small text-muted"><?= e($r['source_type'] ?? '') ?><?= !empty($r['source_id']) ? '-' . (int) $r['source_id'] : '' ?></td>
                    <td class="text-end fw-bold"><?= e(qty($r['quantity'])) ?></td>
                    <td class="text-end"><?= e(money($r['cost'])) ?></td>
                    <td class="text-end"><?= e(money((float) $r['quantity'] > 0 ? $r['cost'] / $r['quantity'] : 0)) ?></td>
                    <td><?= e($r['unit_name'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light fw-bold">
            <tr><td colspan="4">ИТОГО</td><td class="text-end"><?= e(qty($totalQty)) ?></td><td class="text-end"><?= e(money($totalCost)) ?></td><td colspan="2"></td></tr>
            </tfoot>
        </table>
    </div>
</div>
