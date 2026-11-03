<?php
/** Пагинация: $pagination = ['total','page','per_page','last_page']; query-параметры сохраняются. */
use Uchet\Core\Request;

$total = (int) ($pagination['total'] ?? 0);
$page = (int) ($pagination['page'] ?? 1);
$lastPage = (int) ($pagination['last_page'] ?? 1);
if ($lastPage <= 1 && $total <= ($pagination['per_page'] ?? 20)) {
    return;
}
$query = Request::all();
unset($query['page']);
$makeUrl = static function (int $p) use ($query): string {
    return '?' . http_build_query($query + ['page' => $p]);
};
?>
<div class="d-flex justify-content-between align-items-center mt-2 pagination-bar">
    <div class="text-muted small">Всего записей: <?= $total ?> · стр. <?= $page ?> из <?= $lastPage ?></div>
    <nav>
        <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= e($makeUrl(max(1, $page - 1))) ?>">«</a>
            </li>
            <?php for ($p = max(1, $page - 2); $p <= min($lastPage, $page + 2); $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= e($makeUrl($p)) ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $lastPage ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= e($makeUrl(min($lastPage, $page + 1))) ?>">»</a>
            </li>
        </ul>
    </nav>
</div>
