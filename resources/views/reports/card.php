<?php
/** Карточка товара.
 * @var string $title @var array $nom @var array $stock @var array $movements @var string $from @var string $to
 */
?>
<div class="card-1c">
    <div class="card-1c-header"><span><?= e($title) ?> — <?= e($nom['name']) ?></span></div>
    <div class="card-1c-body">
        <table class="table table-sm doc-rekvizity">
            <tbody>
            <tr><td class="text-muted" style="width:200px">Артикул</td><td class="fw-bold"><?= e($nom['article']) ?></td>
                <td class="text-muted" style="width:200px">Ед. измерения</td><td><?= e($nom['unit_name'] ?? '') ?></td></tr>
            <tr><td class="text-muted">Категория</td><td><?= e($nom['category_name'] ?? '') ?></td>
                <td class="text-muted">НДС</td><td><?= e(rtrim(rtrim(number_format((float) $nom['vat_rate'], 2, '.', ''), '0'), '.')) ?>%</td></tr>
            <tr><td class="text-muted">Учётная цена</td><td><?= e(money($nom['purchase_price'])) ?></td>
                <td class="text-muted">Мин. остаток</td><td><?= e(qty($nom['min_stock'])) ?></td></tr>
            </tbody>
        </table>

        <h6 class="section-label">Остатки по складам и партиям</h6>
        <table class="table table-sm table-1c">
            <thead><tr><th>Склад</th><th>Партия</th><th class="text-end">Количество</th><th class="text-end">Себестоимость</th><th class="text-end">Цена ед.</th></tr></thead>
            <tbody>
            <?php $tq = 0.0; $tc = 0.0; ?>
            <?php foreach ($stock as $s): $tq += (float) $s['quantity']; $tc += (float) $s['cost']; ?>
                <tr>
                    <td><?= e($s['warehouse_code'] . ' ' . $s['warehouse_name']) ?></td>
                    <td class="small text-muted"><?= e($s['source_type'] ?? '') ?><?= !empty($s['source_id']) ? '-' . (int) $s['source_id'] : '' ?></td>
                    <td class="text-end fw-bold"><?= e(qty($s['quantity'])) ?></td>
                    <td class="text-end"><?= e(money($s['cost'])) ?></td>
                    <td class="text-end"><?= e(money((float) $s['quantity'] > 0 ? $s['cost'] / $s['quantity'] : 0)) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($stock === []): ?><tr><td colspan="5" class="text-center text-muted">Остатков нет</td></tr><?php endif; ?>
            </tbody>
            <tfoot class="table-light fw-bold"><tr><td colspan="2">ИТОГО</td><td class="text-end"><?= e(qty($tq)) ?></td><td class="text-end"><?= e(money($tc)) ?></td><td></td></tr></tfoot>
        </table>

        <h6 class="section-label">Движения с <?= e(d($from)) ?> по <?= e(d($to)) ?></h6>
        <form method="get" class="filters-bar">
            <label class="d-flex align-items-center gap-1 small">с <input type="date" name="date_from" value="<?= e($from) ?>" class="form-control form-control-sm"></label>
            <label class="d-flex align-items-center gap-1 small">по <input type="date" name="date_to" value="<?= e($to) ?>" class="form-control form-control-sm"></label>
            <button class="btn btn-sm btn-outline-primary" type="submit">Показать</button>
        </form>
        <table class="table table-sm table-1c">
            <thead><tr><th>Дата</th><th>Документ</th><th>Склад</th><th class="text-end">Приход</th><th class="text-end">Расход</th><th class="text-end">Сумма</th></tr></thead>
            <tbody>
            <?php foreach ($movements as $m): ?>
                <tr>
                    <td class="text-nowrap"><?= e(dt($m['doc_date'])) ?></td>
                    <td class="small"><?= e($m['document_type']) ?>-<?= (int) $m['document_id'] ?></td>
                    <td><?= e($m['warehouse_code'] . ' ' . $m['warehouse_name']) ?></td>
                    <td class="text-end text-success"><?= (float) $m['quantity_in'] > 0 ? e(qty($m['quantity_in'])) : '' ?></td>
                    <td class="text-end text-danger"><?= (float) $m['quantity_out'] > 0 ? e(qty($m['quantity_out'])) : '' ?></td>
                    <td class="text-end"><?= (float) $m['amount_in'] > 0 ? e(money($m['amount_in'])) : ((float) $m['amount_out'] > 0 ? e(money($m['amount_out'])) : '') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($movements === []): ?><tr><td colspan="6" class="text-center text-muted">Движений нет</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
