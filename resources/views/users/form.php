<?php
/** @var string $title @var string $mode @var array $values @var array $roles @var int|null $id */
$action = $mode === 'create' ? url('users.store') : url('users.update', ['id' => $id]);
?>
<div class="card-1c">
    <div class="card-1c-header"><span><?= e($title) ?></span></div>
    <div class="card-1c-body">
        <form method="post" action="<?= e($action) ?>">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Логин <span class="text-danger">*</span></label>
                    <input type="text" name="login" class="form-control <?= error_for('login') ? 'is-invalid' : '' ?>" value="<?= e(old('login', $values['login'] ?? '')) ?>" required maxlength="64">
                    <?php if ($e = error_for('login')): ?><div class="invalid-feedback"><?= e($e) ?></div><?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label">ФИО <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" class="form-control" value="<?= e(old('full_name', $values['full_name'] ?? '')) ?>" required maxlength="255">
                </div>
                <div class="col-md-4">
                    <label class="form-label">E-mail</label>
                    <input type="email" name="email" class="form-control" value="<?= e(old('email', $values['email'] ?? '')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Пароль <?= $mode === 'create' ? '<span class="text-danger">*</span>' : '(оставьте пустым, чтобы не менять)' ?></label>
                    <input type="password" name="password" class="form-control" <?= $mode === 'create' ? 'required' : '' ?> minlength="6">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Роль <span class="text-danger">*</span></label>
                    <select name="role_id" class="form-select" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= (int) $r->id ?>" <?= (string) old('role_id', $values['role_id'] ?? '') === (string) $r->id ? 'selected' : '' ?>><?= e($r->name) ?> (<?= e($r->code) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="f-active" <?= !isset($values['is_active']) || (int) $values['is_active'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="f-active">Активен</label>
                    </div>
                </div>
            </div>
            <div class="cmdbar mt-4">
                <button type="submit" class="btn btn-warning fw-bold">Сохранить</button>
                <a href="<?= e(url('users.index')) ?>" class="btn btn-outline-secondary">Отмена</a>
            </div>
        </form>
    </div>
</div>
