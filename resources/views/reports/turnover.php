<?php
/** Обороты по складам за период: начало / приход / расход / конец.
 * @var string $title @var array $rows @var string $from @var string $to @var int|null $warehouseId @var array $warehouses
 */
?>
<div class="card-1c">
    <div class="card-1c-header"><span><?= e($title) ?></span></div>
    <form method="get" class="filters-bar">
        <label class="d-flex align-items-center gap-1 small">с <input type="date" name="date_from" value="<?= e($from) ?>" class="form-control form-control-sm"></label>
        <label class="d-flex align-items-center gap-1 small">по <input type="date" name="date_to" value="<?= e($to) ?>" class="form-control form-control-sm"></label>
        <select name="warehouse_id" class="form-select form-select-sm" style="width:200px">
            <option value="">Все склады</option>
            <?php foreach ($warehouses as $w): ?>
                <option value="<?= (int) $w['id'] ?>" <?= $warehouseId === (int) $w['id'] ? 'selected' : '' ?>><?= e($w['code'] . ' ' . $w['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-outline-primary" type="submit">Показать</button>
    </form>
    <div class="table-responsive">
        <table class="table table-sm table-1c mb-0">
            <thead><tr>
                <th>Склад</th><th>Артикул</th><th>Номенклатура</th>
                <th class="text-end">Начало, кол-во</th><th class="text-end">Приход</th><th class="text-end">Расход</th><th class="text-end">Конец, кол-во</th>
                <th class="text-end">Начало, сумма</th><th class="text-end">Конец, сумма</th>
            </tr></thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">Оборотов за период нет</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <?php $whName = ''; foreach ($warehouses as $w) { if ((int) $w['id'] === (int) $r['warehouse_id']) { $whName = $w['code'] . ' ' . $w['name']; } } ?>
                <tr>
                    <td><?= e($whName) ?></td>
                    <td><a href="<?= e(url('reports.card', ['id' => $r['nomenclature_id']])) ?>"><?= e($r['article']) ?></a></td>
                    <td><?= e($r['nom_name']) ?></td>
                    <td class="text-end"><?= e(qty($r['start_q'])) ?></td>
                    <td class="text-end text-success"><?= e(qty($r['in_q'])) ?></td>
                    <td class="text-end text-danger"><?= e(qty($r['out_q'])) ?></td>
                    <td class="text-end fw-bold"><?= e(qty($r['end_q'])) ?></td>
                    <td class="text-end"><?= e(money($r['start_c'])) ?></td>
                    <td class="text-end"><?= e(money($r['end_c'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
