<?php
/** @var string $title @var array $users @var array $roles (модели) */
?>
<div class="card-1c">
    <div class="card-1c-header">
        <span><?= e($title) ?></span>
        <?php if (can('users.create')): ?>
            <a href="<?= e(url('users.create')) ?>" class="btn btn-sm btn-warning fw-bold">+ Создать</a>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-1c mb-0">
            <thead><tr><th>Логин</th><th>ФИО</th><th>E-mail</th><th>Роль</th><th>Активен</th><th>Последний вход</th><th class="text-end">Действия</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td class="fw-bold"><?= e($u['login']) ?></td>
                    <td><?= e($u['full_name']) ?></td>
                    <td><?= e($u['email'] ?? '') ?></td>
                    <td><span class="badge text-bg-info"><?= e($u['role_code']) ?></span> <?= e($u['role_name']) ?></td>
                    <td><?= (int) $u['is_active'] ? 'да' : 'нет' ?></td>
                    <td><?= !empty($u['last_login_at']) ? e(dt($u['last_login_at'])) : '—' ?></td>
                    <td class="text-end text-nowrap">
                        <?php if (can('users.update')): ?>
                            <a href="<?= e(url('users.edit', ['id' => $u['id']])) ?>" class="btn btn-sm btn-outline-secondary">Изм.</a>
                        <?php endif; ?>
                        <?php if (can('users.delete') && (int) $u['id'] !== (int) (user()['id'] ?? 0)): ?>
                            <form method="post" action="<?= e(url('users.destroy', ['id' => $u['id']])) ?>" class="d-inline" onsubmit="return confirm('Удалить пользователя?')">
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
    <div class="card-1c-body">
        <h6 class="section-label">Роли и права</h6>
        <table class="table table-sm table-1c">
            <thead><tr><th>Роль</th><th>Код</th><th>Права (JSON)</th></tr></thead>
            <tbody>
            <?php foreach ($roles as $r): ?>
                <tr><td><?= e($r->name) ?></td><td><code><?= e($r->code) ?></code></td>
                    <td class="small"><code><?= e(mb_strimwidth((string) $r->permissions, 0, 300, '…')) ?></code></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
