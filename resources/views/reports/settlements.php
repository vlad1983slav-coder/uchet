<?php
/** Взаиморасчёты с контрагентами.
 * @var string $title @var array $rows @var array|null $history @var int|null $cpId @var array $counterparties
 */
?>
<div class="card-1c">
    <div class="card-1c-header"><span><?= e($title) ?></span>
        <form method="get" class="d-flex gap-1">
            <select name="counterparty_id" class="form-select form-select-sm" style="width:280px">
                <option value="">Все контрагенты</option>
                <?php foreach ($counterparties as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= $cpId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-outline-primary" type="submit">Показать</button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-1c mb-0">
            <thead><tr><th>ИНН</th><th>Контрагент</th><th>Тип</th><th class="text-end">Баланс (нам должны + / мы должны −)</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr class="<?= $cpId === (int) $r['id'] ? 'table-active' : '' ?>">
                    <td><?= e($r['inn']) ?></td>
                    <td><a href="?counterparty_id=<?= (int) $r['id'] ?>"><?= e($r['name']) ?></a></td>
                    <td><?= e($r['type']) ?></td>
                    <td class="text-end fw-bold <?= (float) $r['balance'] > 0 ? 'text-success' : ((float) $r['balance'] < 0 ? 'text-danger' : '') ?>">
                        <?= e(money($r['balance'])) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($history !== null): ?>
        <h6 class="section-label">История по контрагенту</h6>
        <table class="table table-sm table-1c">
            <thead><tr><th>Дата</th><th>Документ</th><th class="text-end">Сумма</th></tr></thead>
            <tbody>
            <?php $bal = 0.0; foreach ($history as $h): $bal += (float) $h['amount']; ?>
                <tr>
                    <td class="text-nowrap"><?= e(dt($h['doc_date'])) ?></td>
                    <td class="small"><?= e($h['document_type']) ?>-<?= (int) $h['document_id'] ?></td>
                    <td class="text-end"><?= e(money($h['amount'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light fw-bold"><tr><td colspan="2">Итого баланс</td><td class="text-end"><?= e(money($bal)) ?></td></tr></tfoot>
        </table>
    <?php endif; ?>
</div>
