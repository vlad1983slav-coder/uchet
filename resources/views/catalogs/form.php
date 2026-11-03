<?php
/** Универсальная форма справочника.
 * @var string $title @var string $routeBase @var string $mode @var array $fields @var array $values @var int|null $id
 */
$action = $mode === 'create' ? url($routeBase . '.store') : url($routeBase . '.update', ['id' => $id]);
?>
<div class="card-1c">
    <div class="card-1c-header"><span><?= e($title) ?></span></div>
    <div class="card-1c-body">
        <form method="post" action="<?= e($action) ?>">
            <?= csrf_field() ?>
            <div class="row g-3">
                <?php foreach ($fields as $f): ?>
                    <?php
                    $name = $f['name'];
                    $value = old($name, $values[$name] ?? ($f['default'] ?? ''));
                    $err = error_for($name);
                    $cls = 'form-control' . ($err ? ' is-invalid' : '');
                    if (($f['type'] ?? 'text') === 'checkbox') { $cls = 'form-check-input' . ($err ? ' is-invalid' : ''); }
                    ?>
                    <div class="col-md-<?= in_array($f['type'] ?? '', ['textarea'], true) ? 12 : 6 ?>">
                        <label class="form-label" for="f-<?= e($name) ?>">
                            <?= e($f['label']) ?><?= !empty($f['required']) ? ' <span class="text-danger">*</span>' : '' ?>
                        </label>
                        <?php if (($f['type'] ?? 'text') === 'select'): ?>
                            <select id="f-<?= e($name) ?>" name="<?= e($name) ?>" class="<?= $cls ?>">
                                <?php if (!empty($f['empty'])): ?>
                                    <option value=""><?= e($f['empty']) ?></option>
                                <?php endif; ?>
                                <?php foreach (($f['options'] ?? []) as $optValue => $optLabel): ?>
                                    <option value="<?= e((string) $optValue) ?>" <?= (string) $value === (string) $optValue ? 'selected' : '' ?>>
                                        <?= e($optLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif (($f['type'] ?? '') === 'checkbox'): ?>
                            <div class="form-check">
                                <input type="checkbox" id="f-<?= e($name) ?>" name="<?= e($name) ?>" value="1" class="<?= $cls ?>" <?= !empty($value) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="f-<?= e($name) ?>"><?= e($f['label']) ?></label>
                            </div>
                        <?php elseif (($f['type'] ?? '') === 'textarea'): ?>
                            <textarea id="f-<?= e($name) ?>" name="<?= e($name) ?>" class="<?= $cls ?>" rows="3"><?= e($value) ?></textarea>
                        <?php else: ?>
                            <input type="<?= e($f['type'] ?? 'text') ?>" id="f-<?= e($name) ?>" name="<?= e($name) ?>"
                                   value="<?= e($value) ?>" class="<?= $cls ?>"
                                   <?= isset($f['step']) ? 'step="' . e((string) $f['step']) . '"' : '' ?>
                                   <?= isset($f['min']) ? 'min="' . e((string) $f['min']) . '"' : '' ?>
                                   <?= isset($f['max']) ? 'max="' . e((string) $f['max']) . '"' : '' ?>
                                   <?= isset($f['maxlength']) ? 'maxlength="' . (int) $f['maxlength'] . '"' : '' ?>
                                   <?= !empty($f['required']) ? 'required' : '' ?>>
                        <?php endif; ?>
                        <?php if ($err): ?><div class="invalid-feedback"><?= e($err) ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="cmdbar mt-4">
                <button type="submit" class="btn btn-warning fw-bold">Сохранить</button>
                <a href="<?= e(url($routeBase . '.index')) ?>" class="btn btn-outline-secondary">Отмена</a>
            </div>
        </form>
    </div>
</div>
