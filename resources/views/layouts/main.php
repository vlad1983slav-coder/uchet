<?php
/** @var string $content @var string $title @var array|null $currentUser */
use Uchet\Core\Request;

$path = Request::path();
$menu = [
    'Справочники' => [
        ['label' => 'Номенклатура', 'url' => url('catalogs.nomenclature.index'), 'match' => '/catalogs/nomenclature', 'perm' => 'catalogs.read'],
        ['label' => 'Склады', 'url' => url('catalogs.warehouses.index'), 'match' => '/catalogs/warehouses', 'perm' => 'catalogs.read'],
        ['label' => 'Контрагенты', 'url' => url('catalogs.counterparties.index'), 'match' => '/catalogs/counterparties', 'perm' => 'catalogs.read'],
        ['label' => 'Единицы измерения', 'url' => url('catalogs.units.index'), 'match' => '/catalogs/units', 'perm' => 'catalogs.read'],
        ['label' => 'Категории номенклатуры', 'url' => url('catalogs.categories.index'), 'match' => '/catalogs/categories', 'perm' => 'catalogs.read'],
    ],
    'Документы' => [
        ['label' => 'Приходные накладные (ПН)', 'url' => url('receipts.index'), 'match' => '/documents/receipts', 'perm' => 'documents.read'],
        ['label' => 'Расходные накладные (РН)', 'url' => url('issues.index'), 'match' => '/documents/issues', 'perm' => 'documents.read'],
        ['label' => 'Перемещения (ПМ)', 'url' => url('moves.index'), 'match' => '/documents/moves', 'perm' => 'documents.read'],
        ['label' => 'Инвентаризации (ИНВ)', 'url' => url('inventories.index'), 'match' => '/documents/inventories', 'perm' => 'documents.read'],
        ['label' => 'Возвраты (ВП/ВК)', 'url' => url('returns.index'), 'match' => '/documents/returns', 'perm' => 'documents.read'],
    ],
    'Отчёты' => [
        ['label' => 'Остатки товаров', 'url' => url('reports.stock'), 'match' => '/reports/stock', 'perm' => 'reports.read'],
        ['label' => 'Движения за период', 'url' => url('reports.movements'), 'match' => '/reports/movements', 'perm' => 'reports.read'],
        ['label' => 'Обороты по складам', 'url' => url('reports.turnover'), 'match' => '/reports/turnover', 'perm' => 'reports.read'],
        ['label' => 'Взаиморасчёты', 'url' => url('reports.settlements'), 'match' => '/reports/settlements', 'perm' => 'reports.read'],
    ],
    'Администрирование' => [
        ['label' => 'Пользователи', 'url' => url('users.index'), 'match' => '/users', 'perm' => 'users.read'],
    ],
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title ?? 'Главная') ?> — <?= e($appName ?? 'UCHET') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/1c-style.css')) ?>?v=1">
</head>
<body>
<div class="app-shell">
    <!-- Левый навигатор в стиле 1С -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <span class="brand-mark">UCHET</span>
            <span class="brand-sub">складской учёт</span>
        </div>
        <nav class="navigator">
            <?php foreach ($menu as $section => $items): ?>
                <?php $visible = array_filter($items, static fn ($i) => can($i['perm'])); ?>
                <?php if ($visible === []) continue; ?>
                <div class="nav-section"><?= e($section) ?></div>
                <?php foreach ($visible as $item): ?>
                    <a class="nav-item <?= str_starts_with($path, $item['match']) ? 'active' : '' ?>" href="<?= e($item['url']) ?>">
                        <?= e($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <?php if ($currentUser): ?>
                <div class="user-chip" title="<?= e($currentUser['full_name']) ?>">
                    <span class="user-avatar"><?= e(mb_strtoupper(mb_substr((string) $currentUser['full_name'], 0, 1))) ?></span>
                    <span class="user-meta">
                        <strong><?= e($currentUser['full_name']) ?></strong>
                        <small><?= e($currentUser['role_code'] ?? '') ?></small>
                    </span>
                </div>
                <form method="post" action="<?= e(url('logout')) ?>" class="logout-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Выйти</button>
                </form>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Правая область: верхняя панель + контент -->
    <div class="main-area">
        <header class="topbar">
            <div class="topbar-title"><?= e($title ?? '') ?></div>
            <div class="topbar-right">
                <span class="badge text-bg-light"><?= e($currentUser['login'] ?? '') ?></span>
            </div>
        </header>
        <main class="content">
            <?php require __DIR__ . '/../partials/flash.php'; ?>
            <?= $content ?>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('js/app.js')) ?>?v=1"></script>
</body>
</html>
