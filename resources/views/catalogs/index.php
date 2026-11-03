<?php
/** Универсальный список справочника.
 * @var string $title @var string $routeBase @var array $columns @var array $rows @var array $pagination @var string $q
 * @var bool $canCreate @var bool $canDelete
 */
use Uchet\Core\Request;
?>
<div class="card-1c">
    <div class="card-1c-header">
        <span><?= e($title) ?></span>
        <div class="d-flex gap-2">
            <form method="get" class="d-flex gap-1" role="search">
                <input type="search" name="q" value="<?= e($q) ?>" class="form-control form-control-sm" placeholder="Поиск...">
                <button class="btn btn-sm btn-outline-primary" type="submit">Найти</button>
            </form>
            <?php if ($canCreate): ?>
                <a href="<?= e(url($routeBase . '.create')) ?>" class="btn btn-sm btn-warning fw-bold">+ Создать</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-1c mb-0">
            <thead>
            <tr>
                <?php foreach ($columns as $col): ?>
                    <?php $key = $col['key']; $sortable = !empty($col['sortable']);
                    $isSorted = Request::sort() === $key;
                    $nextOrder = $isSorted && Request::order() === 'ASC' ? 'desc' : 'asc'; ?>
                    <th>
                        <?php if ($sortable): ?>
                            <a href="?<?= e(http_build_query(Request::all() + ['sort' => $key, 'order' => $nextOrder, 'page' => 1])) ?>" class="sort-link">
                                <?= e($col['label']) ?><?= $isSorted ? (Request::order() === 'ASC' ? ' ▲' : ' ▼') : '' ?>
                            </a>
                        <?php else: ?>
                            <?= e($col['label']) ?>
                        <?php endif; ?>
                    </th>
                <?php endforeach; ?>
                <th class="text-end">Действия</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="<?= count($columns) + 1 ?>" class="text-center text-muted py-4">Записей не найдено</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <?php foreach ($columns as $col): ?>
                        <td><?= e($row[$col['key']] ?? '') ?></td>
                    <?php endforeach; ?>
                    <td class="text-end text-nowrap">
                        <?php if (can('catalogs.update')): ?>
                            <a href="<?= e(url($routeBase . '.edit', ['id' => $row['id']])) ?>" class="btn btn-sm btn-outline-secondary">Изм.</a>
                        <?php endif; ?>
                        <?php if ($canDelete): ?>
                            <form method="post" action="<?= e(url($routeBase . '.destroy', ['id' => $row['id']])) ?>" class="d-inline"
                                  onsubmit="return confirm('Удалить запись?')">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger">Удалить</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php $pagination = $pagination; require __DIR__ . '/../partials/pagination.php'; ?>
</div>
