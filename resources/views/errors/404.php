<?php /** @var int $code @var string $message @var ?Throwable $exception */ ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= (int) $code ?> — <?= e($appName ?? 'UCHET') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5 text-center">
    <h1 class="display-4 text-danger"><?= (int) $code ?></h1>
    <p class="lead"><?= e($message) ?></p>
    <a href="/" class="btn btn-outline-secondary">На главную</a>
    <?php if (!empty($exception)): ?>
        <pre class="text-start small bg-white border p-3 mt-4"><?= e($exception->getMessage() . "\n" . $exception->getTraceAsString()) ?></pre>
    <?php endif; ?>
</div>
</body>
</html>
