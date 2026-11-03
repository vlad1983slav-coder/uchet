<?php
/** Печатная форма накладной (HTML; в PDF — если установлен dompdf).
 * @var string $title @var string $type @var array $header @var array $lines
 * @var array $warehouses (по id) @var array $counterparties (по id) @var string $orgName @var string $orgInn
 */
$whMap = $warehouses;
$cpMap = $counterparties;
$isInv = $type === 'inventories';
$whName = $type === 'moves'
    ? ($whMap[(int) $header['warehouse_from_id']]['name'] ?? '') . ' → ' . ($whMap[(int) $header['warehouse_to_id']]['name'] ?? '')
    : ($whMap[(int) $header['warehouse_id']]['name'] ?? '');
$cpName = $cpMap[(int) ($header['counterparty_id'] ?? 0)]['name'] ?? '';
$cpInn = $cpMap[(int) ($header['counterparty_id'] ?? 0)]['inn'] ?? '';
$typeLabels = [
    'receipts' => 'Приходная накладная', 'issues' => 'Расходная накладная',
    'moves' => 'Накладная на перемещение', 'inventories' => 'Акт инвентаризации',
    'returns' => 'Возвратная накладная',
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= e($title) ?></title>
    <style>
        body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 12px; color: #000; margin: 24px; }
        h1 { font-size: 16px; text-align: center; margin: 0 0 4px; }
        .org { text-align: center; font-size: 12px; margin-bottom: 16px; }
        .meta { width: 100%; margin-bottom: 12px; border-collapse: collapse; }
        .meta td { padding: 2px 6px; vertical-align: top; }
        .meta .lbl { color: #555; width: 140px; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.lines th, table.lines td { border: 1px solid #000; padding: 4px 6px; }
        table.lines th { background: #eee; text-align: left; }
        .num { text-align: right; }
        .total { text-align: right; font-weight: bold; margin-top: 6px; }
        .signs { width: 100%; margin-top: 48px; border-collapse: collapse; }
        .signs td { width: 50%; padding: 4px 12px; }
        .sign-line { border-bottom: 1px solid #000; height: 20px; margin-bottom: 4px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
<div class="no-print" style="margin-bottom:12px">
    <button onclick="window.print()">Печать / Сохранить как PDF</button>
</div>
<div class="org">
    <strong><?= e($orgName) ?></strong><?= $orgInn !== '' ? ' · ИНН ' . e($orgInn) : '' ?>
</div>
<h1><?= e($typeLabels[$type] ?? 'Документ') ?> № <?= e($header['number']) ?> от <?= e(d($header['doc_date'])) ?></h1>
<table class="meta">
    <tr><td class="lbl">Склад:</td><td><?= e($whName) ?></td>
        <td class="lbl">Контрагент:</td><td><?= e($cpName) ?><?= $cpInn !== '' ? ' · ИНН ' . e($cpInn) : '' ?></td></tr>
    <?php if (!empty($header['comment'])): ?>
        <tr><td class="lbl">Комментарий:</td><td colspan="3"><?= e($header['comment']) ?></td></tr>
    <?php endif; ?>
</table>
<table class="lines">
    <thead>
    <tr><th style="width:30px">№</th><th>Артикул</th><th>Номенклатура</th><th>Ед.изм.</th>
        <?php if ($isInv): ?>
            <th class="num">Учёт</th><th class="num">Факт</th><th class="num">Отклонение</th>
        <?php else: ?>
            <th class="num">Кол-во</th><th class="num">Цена</th><th class="num">Сумма</th>
        <?php endif; ?>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($lines as $i => $l): ?>
        <tr>
            <td><?= $i + 1 ?></td>
            <td><?= e($l['article']) ?></td>
            <td><?= e($l['nom_name']) ?></td>
            <td><?= e($l['unit_name']) ?></td>
            <?php if ($isInv): ?>
                <td class="num"><?= e(qty($l['book_quantity'])) ?></td>
                <td class="num"><?= e(qty($l['fact_quantity'])) ?></td>
                <td class="num"><?= e(qty((float) $l['fact_quantity'] - (float) $l['book_quantity'])) ?></td>
            <?php else: ?>
                <td class="num"><?= e(qty($l['quantity'])) ?></td>
                <td class="num"><?= e(number_format((float) $l['price'], 2, '.', ' ')) ?></td>
                <td class="num"><?= e(number_format((float) ($l['amount'] ?? $l['quantity'] * $l['price']), 2, '.', ' ')) ?></td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php if (!$isInv): ?>
    <div class="total">Итого: <?= e(number_format((float) $header['total_amount'], 2, '.', ' ')) ?> руб.</div>
<?php endif; ?>
<table class="signs">
    <tr>
        <td><div class="sign-line"></div>Отпустил (МОЛ)</td>
        <td><div class="sign-line"></div>Принял</td>
    </tr>
</table>
</body>
</html>
