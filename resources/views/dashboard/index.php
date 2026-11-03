<?php
/** Главная панель.
 * @var array $totals @var array $byWarehouse @var array $lowStock @var array $docsToday @var array $debt @var array $recent
 */
$typeLabels = ['receipts' => 'ПН', 'issues' => 'РН', 'moves' => 'ПМ', 'inventories' => 'ИНВ', 'returns' => 'ВП/ВК'];
$typeRoutes = ['receipts' => 'receipts.index', 'issues' => 'issues.index', 'moves' => 'moves.index', 'inventories' => 'inventories.index', 'returns' => 'returns.index'];
?>
<div class="row g-3">
    <div class="col-md-3"><div class="kpi-card">
        <div class="kpi-value"><?= e(money($totals['cost'] ?? 0)) ?></div>
        <div class="kpi-label">Стоимость остатков (все склады)</div>
    </div></div>
    <div class="col-md-3"><div class="kpi-card">
        <div class="kpi-value"><?= e(qty($totals['qty'] ?? 0)) ?></div>
        <div class="kpi-label">Количество, ед. (позиций: <?= (int) ($totals['positions'] ?? 0) ?>)</div>
    </div></div>
    <div class="col-md-3"><div class="kpi-card">
        <div class="kpi-value text-success"><?= e(money($debt['recv'] ?? 0)) ?></div>
        <div class="kpi-label">Нам должны (дебиторка)</div>
    </div></div>
    <div class="col-md-3"><div class="kpi-card">
        <div class="kpi-value text-danger"><?= e(money($debt['pay'] ?? 0)) ?></div>
        <div class="kpi-label">Мы должны (кредиторка)</div>
    </div></div>
</div>

<div class="row g-3 mt-1">
    <div class="col-md-6">
        <div class="card-1c">
            <div class="card-1c-header"><span>Остатки по складам</span></div>
            <table class="table table-sm table-1c mb-0">
                <thead><tr><th>Склад</th><th class="text-end">Позиций</th><th class="text-end">Кол-во</th><th class="text-end">Сумма</th></tr></thead>
                <tbody>
                <?php foreach ($byWarehouse as $w): ?>
                    <tr><td><?= e($w['code'] . ' ' . $w['name']) ?></td><td class="text-end"><?= (int) $w['positions'] ?></td>
                        <td class="text-end"><?= e(qty($w['qty'])) ?></td><td class="text-end"><?= e(money($w['cost'])) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card-1c">
            <div class="card-1c-header"><span>Документы сегодня</span></div>
            <table class="table table-sm table-1c mb-0">
                <thead><tr><th>Журнал</th><th class="text-end">Всего</th><th class="text-end">Проведено</th><th class="text-end">Черновиков</th></tr></thead>
                <tbody>
                <?php foreach ($docsToday as $type => $row): ?>
                    <tr><td><a href="<?= e(url($typeRoutes[$type])) ?>"><?= e($typeLabels[$type]) ?></a></td>
                        <td class="text-end"><?= (int) $row['total'] ?></td>
                        <td class="text-end text-success"><?= (int) $row['posted'] ?></td>
                        <td class="text-end text-muted"><?= (int) $row['draft'] ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-md-6">
        <div class="card-1c">
            <div class="card-1c-header"><span>Товары ниже минимального остатка</span></div>
            <table class="table table-sm table-1c mb-0">
                <thead><tr><th>Артикул</th><th>Номенклатура</th><th class="text-end">Остаток</th><th class="text-end">Минимум</th></tr></thead>
                <tbody>
                <?php if ($lowStock === []): ?><tr><td colspan="4" class="text-center text-muted">Все остатки в норме</td></tr><?php endif; ?>
                <?php foreach ($lowStock as $n): ?>
                    <tr>
                        <td><a href="<?= e(url('reports.card', ['id' => $n['id']])) ?>"><?= e($n['article']) ?></a></td>
                        <td><?= e($n['name']) ?></td>
                        <td class="text-end text-danger fw-bold"><?= e(qty($n['qty'])) ?></td>
                        <td class="text-end"><?= e(qty($n['min_stock'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card-1c">
            <div class="card-1c-header"><span>Последние движения</span></div>
            <table class="table table-sm table-1c mb-0">
                <thead><tr><th>Дата</th><th>Товар</th><th>Склад</th><th class="text-end">+/−</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $m): ?>
                    <?php $delta = (float) $m['quantity_in'] - (float) $m['quantity_out']; ?>
                    <tr>
                        <td class="text-nowrap small"><?= e(dt($m['doc_date'])) ?></td>
                        <td class="small"><a href="<?= e(url('reports.card', ['id' => $m['nomenclature_id']])) ?>"><?= e($m['article']) ?></a> <?= e(mb_strimwidth($m['nom_name'], 0, 30, '…')) ?></td>
                        <td class="small"><?= e($m['warehouse_code']) ?></td>
                        <td class="text-end small <?= $delta >= 0 ? 'text-success' : 'text-danger' ?>"><?= $delta >= 0 ? '+' : '' ?><?= e(qty($delta)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
