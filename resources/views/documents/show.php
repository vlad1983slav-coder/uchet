<?php
/** Просмотр документа: шапка, табличная часть, движения (проводки), аудит.
 * @var string $title @var string $type @var string $routeBase @var array $header @var array $lines
 * @var array $movements @var array $audit @var array $warehouses @var array $counterparties
 * @var bool $canEdit @var bool $canPost @var bool $canUnpost @var bool $canDelete
 */
$whMap = array_column($warehouses, 'name', 'id');
$cpMap = array_column($counterparties, 'name', 'id');
$isInv = $type === 'inventories';
$status = $header['status'];
?>
<div class="card-1c">
    <div class="card-1c-header">
        <span><?= e($title) ?></span>
        <?php require __DIR__ . '/../partials/status_badge.php'; ?>
    </div>

    <!-- Панель команд -->
    <div class="cmdbar">
        <?php if ($canEdit): ?>
            <a href="<?= e(url($routeBase . '.edit', ['id' => $header['id']])) ?>" class="btn btn-sm btn-outline-primary">Редактировать</a>
        <?php endif; ?>
        <?php if ($canPost): ?>
            <button type="button" class="btn btn-sm btn-success fw-bold" data-ajax-action="post" data-url="<?= e(url($routeBase . '.post', ['id' => $header['id']])) ?>">Провести</button>
        <?php endif; ?>
        <?php if ($canUnpost): ?>
            <button type="button" class="btn btn-sm btn-warning fw-bold" data-ajax-action="unpost" data-url="<?= e(url($routeBase . '.unpost', ['id' => $header['id']])) ?>">Распровести</button>
        <?php endif; ?>
        <a href="<?= e(url($routeBase . '.print', ['id' => $header['id']])) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">Печать</a>
        <?php if ($canDelete): ?>
            <button type="button" class="btn btn-sm btn-outline-danger" data-ajax-action="delete" data-url="<?= e(url($routeBase . '.destroy', ['id' => $header['id']])) ?>" data-redirect="<?= e(url($routeBase . '.index')) ?>">Удалить</button>
        <?php endif; ?>
        <a href="<?= e(url($routeBase . '.index')) ?>" class="btn btn-sm btn-outline-secondary">К списку</a>
    </div>

    <div class="card-1c-body">
        <!-- Шапка -->
        <table class="table table-sm doc-rekvizity mb-4">
            <tbody>
            <tr><td class="text-muted" style="width:180px">Номер</td><td class="fw-bold"><?= e($header['number']) ?></td>
                <td class="text-muted" style="width:180px">Дата</td><td><?= e(dt($header['doc_date'])) ?></td></tr>
            <tr>
                <td class="text-muted">Склад</td>
                <td>
                    <?php if ($type === 'moves'): ?>
                        <?= e($whMap[(int) $header['warehouse_from_id']] ?? '?') ?> → <?= e($whMap[(int) $header['warehouse_to_id']] ?? '?') ?>
                    <?php else: ?>
                        <?= e($whMap[(int) $header['warehouse_id']] ?? '?') ?>
                    <?php endif; ?>
                </td>
                <?php if (!$isInv): ?>
                    <td class="text-muted">Контрагент</td><td><?= e($cpMap[(int) $header['counterparty_id']] ?? '') ?></td>
                <?php else: ?>
                    <td></td><td></td>
                <?php endif; ?>
            </tr>
            <?php if ($type === 'returns'): ?>
                <tr><td class="text-muted">Направление</td><td><?= $header['direction'] === 'to_supplier' ? 'ВП — возврат поставщику' : 'ВК — возврат от покупателя' ?></td><td></td><td></td></tr>
            <?php endif; ?>
            <tr><td class="text-muted">Комментарий</td><td colspan="3"><?= e($header['comment'] ?? '') ?></td></tr>
            <tr>
                <td class="text-muted">Создал</td><td><?= e($header['created_by'] ?? '') ?></td>
                <td class="text-muted">Проведён</td><td><?= !empty($header['posted_at']) ? e(dt($header['posted_at'])) : '—' ?></td>
            </tr>
            </tbody>
        </table>

        <!-- Табличная часть -->
        <h6 class="section-label">Товары</h6>
        <table class="table table-sm table-1c">
            <thead>
            <tr>
                <th>#</th><th>Артикул</th><th>Номенклатура</th><th>Ед.изм.</th>
                <?php if ($isInv): ?>
                    <th class="text-end">Учёт</th><th class="text-end">Факт</th><th class="text-end">Отклонение</th>
                <?php else: ?>
                    <th class="text-end">Кол-во</th><th class="text-end">Цена</th><th class="text-end">НДС,%</th><th class="text-end">Сумма</th>
                <?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($lines as $i => $line): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($line['article']) ?></td>
                    <td><?= e($line['nom_name']) ?></td>
                    <td><?= e($line['unit_name']) ?></td>
                    <?php if ($isInv): ?>
                        <td class="text-end"><?= e(qty($line['book_quantity'])) ?></td>
                        <td class="text-end"><?= e(qty($line['fact_quantity'])) ?></td>
                        <td class="text-end <?= (float)($line['fact_quantity'] - $line['book_quantity']) != 0.0 ? 'text-danger fw-bold' : '' ?>">
                            <?= e(qty((float) $line['fact_quantity'] - (float) $line['book_quantity'])) ?>
                        </td>
                    <?php else: ?>
                        <td class="text-end"><?= e(qty($line['quantity'])) ?></td>
                        <td class="text-end"><?= e(money($line['price'])) ?></td>
                        <td class="text-end"><?= e(rtrim(rtrim(number_format((float) $line['vat_rate'], 2, '.', ''), '0'), '.')) ?></td>
                        <td class="text-end"><?= e(money($line['amount'] ?? ((float) $line['quantity'] * (float) $line['price']))) ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <?php if (!$isInv): ?>
                <tfoot class="table-light fw-bold">
                <tr><td colspan="4"></td>
                    <td class="text-end"><?= e(qty($header['total_quantity'])) ?></td>
                    <td></td><td></td>
                    <td class="text-end"><?= e(money($header['total_amount'])) ?></td>
                </tr>
                </tfoot>
            <?php endif; ?>
        </table>

        <!-- Проводки (регистр движений), если документ проведён -->
        <?php if ($movements !== []): ?>
            <h6 class="section-label">Проводки (регистр движений)</h6>
            <table class="table table-sm table-1c">
                <thead><tr><th>Дата</th><th>Артикул</th><th>Номенклатура</th><th class="text-end">Приход</th><th class="text-end">Расход</th><th class="text-end">Сумма прихода</th><th class="text-end">Сумма расхода</th></tr></thead>
                <tbody>
                <?php foreach ($movements as $mv): ?>
                    <tr>
                        <td class="text-nowrap"><?= e(dt($mv['doc_date'])) ?></td>
                        <td><?= e($mv['article']) ?></td>
                        <td><?= e($mv['nom_name']) ?></td>
                        <td class="text-end text-success"><?= (float) $mv['quantity_in'] > 0 ? e(qty($mv['quantity_in'])) : '' ?></td>
                        <td class="text-end text-danger"><?= (float) $mv['quantity_out'] > 0 ? e(qty($mv['quantity_out'])) : '' ?></td>
                        <td class="text-end"><?= (float) $mv['amount_in'] > 0 ? e(money($mv['amount_in'])) : '' ?></td>
                        <td class="text-end"><?= (float) $mv['amount_out'] > 0 ? e(money($mv['amount_out'])) : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Аудит -->
        <?php if ($audit !== []): ?>
            <h6 class="section-label">История изменений</h6>
            <table class="table table-sm table-1c">
                <thead><tr><th>Когда</th><th>Кто</th><th>Действие</th><th>Данные</th></tr></thead>
                <tbody>
                <?php foreach ($audit as $a): ?>
                    <tr>
                        <td class="text-nowrap"><?= e(dt($a['created_at'])) ?></td>
                        <td><?= e(trim(($a['full_name'] ?? '') . ' (' . ($a['login'] ?? '') . ')')) ?></td>
                        <td><span class="badge text-bg-secondary"><?= e($a['action']) ?></span></td>
                        <td class="small text-muted" style="max-width:420px">
                            <?php if (!empty($a['new_data'])): ?><code><?= e(mb_strimwidth((string) $a['new_data'], 0, 200, '…')) ?></code><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
