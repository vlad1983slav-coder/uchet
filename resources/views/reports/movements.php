<?php
/** Движения товаров за период.
 * @var string $title @var array $rows @var array $f @var array $warehouses @var array $nomenclatures @var array $types
 */
?>
<div class="card-1c">
    <div class="card-1c-header"><span><?= e($title) ?></span></div>
    <form method="get" class="filters-bar">
        <label class="d-flex align-items-center gap-1 small">с <input type="date" name="date_from" value="<?= e($f['date_from']) ?>" class="form-control form-control-sm"></label>
        <label class="d-flex align-items-center gap-1 small">по <input type="date" name="date_to" value="<?= e($f['date_to']) ?>" class="form-control form-control-sm"></label>
        <select name="nomenclature_id" class="form-select form-select-sm" style="width:240px">
            <option value="">Вся номенклатура</option>
            <?php foreach ($nomenclatures as $n): ?>
                <option value="<?= (int) $n->id ?>" <?= (int) ($f['nomenclature_id'] ?? 0) === (int) $n->id ? 'selected' : '' ?>><?= e($n->article . ' ' . $n->name) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="warehouse_id" class="form-select form-select-sm" style="width:180px">
            <option value="">Все склады</option>
            <?php foreach ($warehouses as $w): ?>
                <option value="<?= (int) $w['id'] ?>" <?= (int) ($f['warehouse_id'] ?? 0) === (int) $w['id'] ? 'selected' : '' ?>><?= e($w['code'] . ' ' . $w['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="document_type" class="form-select form-select-sm" style="width:190px">
            <option value="">Все документы</option>
            <?php foreach ($types as $val => $label): ?>
                <option value="<?= e($val) ?>" <?= ($f['document_type'] ?? '') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-outline-primary" type="submit">Показать</button>
    </form>
    <div class="table-responsive">
        <table class="table table-sm table-1c mb-0">
            <thead><tr>
                <th>Дата</th><th>Документ</th><th>Склад</th><th>Артикул</th><th>Номенклатура</th>
                <th class="text-end">Приход</th><th class="text-end">Расход</th><th class="text-end">Сумма прихода</th><th class="text-end">Сумма расхода</th>
            </tr></thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">Движений за период нет</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $m): ?>
                <tr>
                    <td class="text-nowrap"><?= e(dt($m['doc_date'])) ?></td>
                    <td class="small"><?= e($m['document_type']) ?>-<?= (int) $m['document_id'] ?></td>
                    <td><?= e($m['warehouse_code'] . ' ' . $m['warehouse_name']) ?></td>
                    <td><a href="<?= e(url('reports.card', ['id' => $m['nomenclature_id']])) ?>"><?= e($m['article']) ?></a></td>
                    <td><?= e($m['nom_name']) ?></td>
                    <td class="text-end text-success"><?= (float) $m['quantity_in'] > 0 ? e(qty($m['quantity_in'])) : '' ?></td>
                    <td class="text-end text-danger"><?= (float) $m['quantity_out'] > 0 ? e(qty($m['quantity_out'])) : '' ?></td>
                    <td class="text-end"><?= (float) $m['amount_in'] > 0 ? e(money($m['amount_in'])) : '' ?></td>
                    <td class="text-end"><?= (float) $m['amount_out'] > 0 ? e(money($m['amount_out'])) : '' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
