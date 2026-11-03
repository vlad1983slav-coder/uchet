<?php /** Бейдж статуса документа 1С: серый — черновик, зелёный — проведён, красный — ошибка */
$status = $status ?? 'draft';
$map = [
    'draft' => ['secondary', 'Черновик'],
    'posted' => ['success', 'Проведён'],
    'error' => ['danger', 'Ошибка'],
];
[$cls, $label] = $map[$status] ?? ['secondary', $status];
?>
<span class="badge status-badge status-<?= e($status) ?> text-bg-<?= $cls ?>"><?= e($label) ?></span>
