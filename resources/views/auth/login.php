<?php
/** @var string $title Страница входа (standalone, без layout 1С) */
$title = $title ?? 'Вход';
$errors = errors();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> — <?= e($appName ?? 'UCHET') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #e9ecef; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .login-card { width: 100%; max-width: 400px; }
        .brand { font-weight: 700; color: #b8860b; letter-spacing: 2px; }
    </style>
</head>
<body>
<div class="card login-card shadow-sm">
    <div class="card-body p-4">
        <div class="text-center mb-3">
            <div class="brand fs-3"><?= e($appName ?? 'UCHET') ?></div>
            <div class="text-muted small">складской учёт · вход в систему</div>
        </div>

        <?php foreach (['success' => 'success', 'info' => 'info', 'error' => 'danger', 'warning' => 'warning'] as $key => $cls): ?>
            <?php if ($msg = flash_message($key)): ?>
                <div class="alert alert-<?= $cls ?> py-2 small"><?= e($msg) ?></div>
            <?php endif; ?>
        <?php endforeach; ?>

        <form method="post" action="<?= e(url('login.post')) ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="login">Логин</label>
                <input type="text" class="form-control <?= isset($errors['login']) ? 'is-invalid' : '' ?>"
                       id="login" name="login" value="<?= e(old('login', '')) ?>" required autofocus>
                <?php if ($err = error_for('login')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Пароль</label>
                <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                       id="password" name="password" required>
                <?php if ($err = error_for('password')): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
            </div>
            <button type="submit" class="btn btn-warning w-100 fw-bold">Войти</button>
        </form>
        <div class="text-muted small text-center mt-3">
            Тестовые входы: admin / admin123 · sklad / sklad123 · manager / manager123
        </div>
    </div>
</div>
</body>
</html>
