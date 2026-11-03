<?php
/** Журнал документов (список в стиле 1С).
 * @var string $title @var string $type @var string $routeBase @var object[] $docs
 * @var array $pagination @var array $filters @var array $warehouses @var array $counterparties
 */
use Uchet\Core\Request;

$whMap = array_column($warehouses, 'name', 'id');
$cpMap = array_column($counterparties, 'name', 'id');
$statuses = ['' => 'Все статусы', 'draft' => 'Черновик', 'posted' => 'Проведён', 'error' => 'Ошибка'];
?>
<div class="card-1c">
    <div class="card-1c-header">
        <span><?= e($title) ?></span>
        <?php if (can('documents.create')): ?>
            <a href="<?= e(url($routeBase . '.create')) ?>" class="btn btn-sm btn-warning fw-bold">+ Создать</a>
        <?php endif; ?>
    </div>

    <!-- Панель фильтров -->
    <form method="get" class="filters-bar" id="doc-filters">
        <input type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" class="form-control form-control-sm" placeholder="№ документа..." style="width:140px">
        <select name="status" class="form-select form-select-sm" style="width:130px">
            <?php foreach ($statuses as $val => $label): ?>
                <option value="<?= e($val) ?>" <?= (string) ($filters['status'] ?? '') === (string) $val ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="warehouse_id" class="form-select form-select-sm" style="width:190px">
            <option value="">Все склады</option>
            <?php foreach ($whMap as $id => $name): ?>
                <option value="<?= (int) $id ?>" <?= (string) ($filters['warehouse_id'] ?? '') === (string) $id ? 'selected' : '' ?>><?= e($name) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="counterparty_id" class="form-select form-select-sm" style="width:200px">
            <option value="">Все контрагенты</option>
            <?php foreach ($cpMap as $id => $name): ?>
                <option value="<?= (int) $id ?>" <?= (string) ($filters['counterparty_id'] ?? '') === (string) $id ? 'selected' : '' ?>><?= e($name) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($type === 'returns'): ?>
            <select name="direction" class="form-select form-select-sm" style="width:170px">
                <option value="">Все возвраты</option>
                <option value="to_supplier" <?= ($filters['direction'] ?? '') === 'to_supplier' ? 'selected' : '' ?>>ВП — поставщику</option>
                <option value="from_customer" <?= ($filters['direction'] ?? '') === 'from_customer' ? 'selected' : '' ?>>ВК — от покупателя</option>
            </select>
        <?php endif; ?>
        <label class="d-flex align-items-center gap-1 small text-muted">с
            <input type="date" name="date_from" value="<?= e($filters['date_from'] ?? '') ?>" class="form-control form-control-sm">
        </label>
        <label class="d-flex align-items-center gap-1 small text-muted">по
            <input type="date" name="date_to" value="<?= e($filters['date_to'] ?? '') ?>" class="form-control form-control-sm">
        </label>
        <button class="btn btn-sm btn-outline-primary" type="submit">Применить</button>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url($routeBase . '.index')) ?>">Сбросить</a>
    </form>

    <div class="table-responsive">
        <table class="table table-sm table-1c table-hover mb-0">
            <thead>
            <tr>
                <th>№</th><th>Дата</th><th>Статус</th>
                <th><?= $type === 'moves' ? 'Со склада → на склад' : 'Склад' ?></th>
                <?php if ($type !== 'inventories'): ?><th>Контрагент</th><?php endif; ?>
                <?php if ($type === 'returns'): ?><th>Направление</th><?php endif; ?>
                <?php if (!in_array($type, ['inventories'], true)): ?><th class="text-end">Кол-во</th><th class="text-end">Сумма</th><?php endif; ?>
                <th class="text-end">Действия</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($docs === []): ?>
                <tr><td colspan="10" class="text-center text-muted py-4">Документов не найдено</td></tr>
            <?php endif; ?>
            <?php foreach ($docs as $doc): ?>
                <?php $status = (string) $doc->status; ?>
                <tr>
                    <td><a href="<?= e(url($routeBase . '.show', ['id' => $doc->id])) ?>" class="fw-bold"><?= e($doc->number) ?></a></td>
                    <td class="text-nowrap"><?= e(dt($doc->doc_date)) ?></td>
                    <td><?php $status = $status; require __DIR__ . '/../partials/status_badge.php'; ?></td>
                    <td>
                        <?php if ($type === 'moves'): ?>
                            <?= e($whMap[(int) $doc->warehouse_from_id] ?? '?') ?> → <?= e($whMap[(int) $doc->warehouse_to_id] ?? '?') ?>
                        <?php else: ?>
                            <?= e($whMap[(int) $doc->warehouse_id] ?? '?') ?>
                        <?php endif; ?>
                    </td>
                    <?php if ($type !== 'inventories'): ?>
                        <td><?= e($cpMap[(int) $doc->counterparty_id] ?? '') ?></td>
                    <?php endif; ?>
                    <?php if ($type === 'returns'): ?>
                        <td><?= $doc->direction === 'to_supplier' ? 'ВП поставщику' : 'ВК от покупателя' ?></td>
                    <?php endif; ?>
                    <?php if ($type !== 'inventories'): ?>
                        <td class="text-end"><?= e(qty($doc->total_quantity)) ?></td>
                        <td class="text-end"><?= e(money($doc->total_amount)) ?></td>
                    <?php endif; ?>
                    <td class="text-end text-nowrap">
                        <a href="<?= e(url($routeBase . '.show', ['id' => $doc->id])) ?>" class="btn btn-sm btn-outline-primary" title="Просмотр">Открыть</a>
                        <?php if ($status !== 'posted' && can('documents.post')): ?>
                            <button type="button" class="btn btn-sm btn-outline-success" title="Провести"
                                    data-ajax-action="post" data-url="<?= e(url($routeBase . '.post', ['id' => $doc->id])) ?>">Провести</button>
                        <?php endif; ?>
                        <?php if ($status === 'posted' && can('documents.unpost')): ?>
                            <button type="button" class="btn btn-sm btn-outline-warning" title="Распровести"
                                    data-ajax-action="unpost" data-url="<?= e(url($routeBase . '.unpost', ['id' => $doc->id])) ?>">Распровести</button>
                        <?php endif; ?>
                        <a href="<?= e(url($routeBase . '.print', ['id' => $doc->id])) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Печатная форма">Печать</a>
                        <?php if ($status !== 'posted' && can('documents.delete')): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger" title="Удалить черновик"
                                    data-ajax-action="delete" data-url="<?= e(url($routeBase . '.destroy', ['id' => $doc->id])) ?>"
                                    data-redirect="<?= e(url($routeBase . '.index')) ?>">Удалить</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php $pagination = $pagination; require __DIR__ . '/../partials/pagination.php'; ?>
</div>
