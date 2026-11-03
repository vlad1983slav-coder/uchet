<?php
/** @var string $title @var string $userName @var string $roleCode — временная заглушка главной (п.6 заменит панелью) */
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> — <?= e($appName ?? 'UCHET') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="card shadow-sm">
        <div class="card-body">
            <h1 class="h4 mb-3"><?= e($appName ?? 'UCHET') ?> — складской учёт</h1>
            <p>Вход выполнен: <strong><?= e($userName) ?></strong> (роль: <code><?= e($roleCode) ?></code>)</p>
            <p class="text-muted">Ядро (п. 3) подключено: сессии, CSRF, RBAC, роутер, PDO, валидация работают.
                Далее — сервис проведения документов (п. 4), контроллеры и интерфейс 1С (п. 5–6).</p>
            <form method="post" action="<?= e(url('logout')) ?>" class="d-inline">
                <?= csrf_field() ?>
                <button class="btn btn-outline-danger btn-sm">Выйти</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
