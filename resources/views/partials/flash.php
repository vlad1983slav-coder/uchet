<?php /** Flash-сообщения: success (зелёный), error (красный), info, warning */ ?>
<?php foreach (['success' => 'alert-success', 'error' => 'alert-danger', 'info' => 'alert-info', 'warning' => 'alert-warning'] as $key => $cls): ?>
    <?php if ($msg = flash_message($key)): ?>
        <div class="alert <?= $cls ?> alert-dismissible fade show py-2" role="alert">
            <?= e($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
        </div>
    <?php endif; ?>
<?php endforeach; ?>
<?php $errs = errors(); ?>
<?php if ($errs !== []): ?>
    <div class="alert alert-danger py-2">
        <strong>Ошибки формы:</strong>
        <ul class="mb-0 small">
            <?php foreach ($errs as $field => $messages): ?>
                <?php foreach ((array) $messages as $m): ?>
                    <li><?= e($m) ?></li>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
