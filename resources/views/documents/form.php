<?php
/** Форма документа: шапка + табличная часть с динамическими строками.
 * @var string $title @var string $type @var string $routeBase @var string $mode ('create'|'edit')
 * @var array $header @var array $lines @var string $status @var string $priceMode
 * @var array $warehouses @var array $counterparties
 */
use Uchet\Core\Request;

$isInv = $type === 'inventories';
$action = $mode === 'create' ? url($routeBase . '.store') : url($routeBase . '.update', ['id' => $header['id'] ?? 0]);
$h = static fn (string $key, $default = '') => old($key, $header[$key] ?? $default);
$whOptions = array_column($warehouses, 'name', 'id');
$cpOptions = array_column($counterparties, 'name', 'id');
?>
<div class="card-1c">
    <div class="card-1c-header"><span><?= e($title) ?></span>
        <?php if ($mode === 'edit'): $status = $status; require __DIR__ . '/../partials/status_badge.php'; endif; ?>
    </div>
    <div class="card-1c-body">
        <form method="post" action="<?= e($action) ?>" id="doc-form" data-price-mode="<?= e($priceMode) ?>" data-doc-type="<?= e($type) ?>">
            <?= csrf_field() ?>

            <!-- ШАПКА -->
            <div class="row g-2 mb-3 doc-header">
                <div class="col-md-3">
                    <label class="form-label">Дата <span class="text-danger">*</span></label>
                    <input type="datetime-local" name="doc_date" class="form-control form-control-sm <?= error_for('doc_date') ? 'is-invalid' : '' ?>"
                           value="<?= e(str_replace(' ', 'T', substr((string) $h('doc_date', date('Y-m-d H:i:s')), 0, 16))) ?>" required>
                    <?php if ($e = error_for('doc_date')): ?><div class="invalid-feedback"><?= e($e) ?></div><?php endif; ?>
                </div>
                <?php if ($type === 'moves'): ?>
                    <div class="col-md-3">
                        <label class="form-label">Со склада <span class="text-danger">*</span></label>
                        <select name="warehouse_from_id" class="form-select form-select-sm" required>
                            <option value="">— выберите —</option>
                            <?php foreach ($whOptions as $id => $name): ?>
                                <option value="<?= (int) $id ?>" <?= (string) $h('warehouse_from_id') === (string) $id ? 'selected' : '' ?>><?= e($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">На склад <span class="text-danger">*</span></label>
                        <select name="warehouse_to_id" class="form-select form-select-sm" required>
                            <option value="">— выберите —</option>
                            <?php foreach ($whOptions as $id => $name): ?>
                                <option value="<?= (int) $id ?>" <?= (string) $h('warehouse_to_id') === (string) $id ? 'selected' : '' ?>><?= e($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <div class="col-md-3">
                        <label class="form-label">Склад <span class="text-danger">*</span></label>
                        <select name="warehouse_id" class="form-select form-select-sm <?= error_for('warehouse_id') ? 'is-invalid' : '' ?>" required <?= $isInv && $mode === 'create' ? '' : '' ?>>
                            <option value="">— выберите —</option>
                            <?php foreach ($whOptions as $id => $name): ?>
                                <option value="<?= (int) $id ?>" <?= (string) $h('warehouse_id') === (string) $id ? 'selected' : '' ?>><?= e($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($e = error_for('warehouse_id')): ?><div class="invalid-feedback"><?= e($e) ?></div><?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if (!$isInv): ?>
                    <div class="col-md-3">
                        <label class="form-label">Контрагент <span class="text-danger">*</span></label>
                        <select name="counterparty_id" class="form-select form-select-sm <?= error_for('counterparty_id') ? 'is-invalid' : '' ?>" required>
                            <option value="">— выберите —</option>
                            <?php foreach ($cpOptions as $id => $name): ?>
                                <option value="<?= (int) $id ?>" <?= (string) $h('counterparty_id') === (string) $id ? 'selected' : '' ?>><?= e($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($e = error_for('counterparty_id')): ?><div class="invalid-feedback"><?= e($e) ?></div><?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($type === 'returns'): ?>
                    <div class="col-md-3">
                        <label class="form-label">Направление <span class="text-danger">*</span></label>
                        <select name="direction" id="direction" class="form-select form-select-sm" required>
                            <option value="to_supplier" <?= $h('direction') === 'to_supplier' ? 'selected' : '' ?>>ВП — возврат поставщику</option>
                            <option value="from_customer" <?= $h('direction') === 'from_customer' ? 'selected' : '' ?>>ВК — возврат от покупателя</option>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="col-md-<?= $isInv ? 6 : 3 ?>">
                    <label class="form-label">Комментарий</label>
                    <input type="text" name="comment" class="form-control form-control-sm" value="<?= e($h('comment')) ?>">
                </div>
            </div>
            <?php if ($isInv && $mode === 'edit'): ?>
                <div class="mb-2">
                    <button type="submit" formaction="<?= e(url($routeBase . '.refill', ['id' => $header['id']])) ?>" class="btn btn-sm btn-outline-primary">
                        Обновить учётные количества по остаткам склада
                    </button>
                </div>
            <?php endif; ?>

            <!-- ТАБЛИЧНАЯ ЧАСТЬ -->
            <table class="table table-sm table-1c lines-table" id="lines-table">
                <thead>
                <tr>
                    <th style="width:26px">#</th>
                    <th style="width:180px">Артикул</th>
                    <th>Номенклатура</th>
                    <th style="width:70px">Ед.изм.</th>
                    <?php if ($isInv): ?>
                        <th style="width:110px" class="text-end">Учёт</th>
                        <th style="width:110px" class="text-end">Факт *</th>
                    <?php else: ?>
                        <th style="width:110px" class="text-end">Количество *</th>
                        <th style="width:120px" class="text-end">Цена</th>
                        <th style="width:80px" class="text-end">НДС,%</th>
                        <th style="width:120px" class="text-end">Сумма</th>
                    <?php endif; ?>
                    <th style="width:40px"></th>
                </tr>
                </thead>
                <tbody id="lines-body">
                <?php foreach ($lines as $i => $line): ?>
                    <?= view_line_row($i, $line, $isInv) ?>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                <tr class="table-light fw-bold">
                    <td colspan="<?= $isInv ? 4 : 4 ?>"></td>
                    <?php if ($isInv): ?>
                        <td class="text-end" id="total-book">0</td>
                        <td class="text-end" id="total-fact">0</td>
                    <?php else: ?>
                        <td class="text-end" id="total-qty">0</td>
                        <td></td><td></td>
                        <td class="text-end" id="total-amount">0.00</td>
                    <?php endif; ?>
                    <td></td>
                </tr>
                </tfoot>
            </table>

            <!-- шаблон новой строки ТЧ для JS -->
            <template id="line-template">
                <?= view_line_row('__INDEX__', [], $isInv, true) ?>
            </template>

            <div class="cmdbar">
                <button type="submit" class="btn btn-warning fw-bold">Сохранить</button>
                <button type="button" class="btn btn-outline-primary" id="add-line">+ Добавить строку</button>
                <a href="<?= e($mode === 'create' ? url($routeBase . '.index') : url($routeBase . '.show', ['id' => $header['id'] ?? 0])) ?>" class="btn btn-outline-secondary">Отмена</a>
            </div>
        </form>
    </div>
</div>

<?php
/**
 * Одна строка ТЧ. Вынесена в функцию, чтобы использовать и для существующих строк, и для <template>.
 *
 * @param int|string $i
 * @param array $line
 */
function view_line_row(int|string $i, array $line, bool $isInv, bool $isTemplate = false): string
{
    $name = static fn (string $field) => 'lines[' . $i . '][' . $field . ']';
    $val = static fn (string $field, $default = '') => $isTemplate ? '' : ($line[$field] ?? $default);
    $qty = $isInv ? ($line['fact_quantity'] ?? '') : ($line['quantity'] ?? '');
    $book = $line['book_quantity'] ?? '';
    ob_start();
    ?>
    <tr class="line-row">
        <td class="line-no"></td>
        <td>
            <input type="hidden" name="<?= e($name('nomenclature_id')) ?>" class="line-nom-id" value="<?= e($val('nomenclature_id')) ?>">
            <input type="text" name="<?= e($name('article')) ?>" class="form-control form-control-sm line-article" autocomplete="off"
                   placeholder="артикул..." value="<?= e($val('article')) ?>" <?= $isTemplate ? 'data-template="1"' : '' ?>>
            <div class="autocomplete-list d-none"></div>
        </td>
        <td class="line-name text-muted small"><?= e($val('nom_name')) ?></td>
        <td class="line-unit text-muted small"><?= e($val('unit_name')) ?></td>
        <?php if ($isInv): ?>
            <td><input type="number" step="0.001" min="0" name="<?= e($name('book_quantity')) ?>" class="form-control form-control-sm text-end line-book" readonly value="<?= e($book) ?>"></td>
            <td><input type="number" step="0.001" min="0" name="<?= e($name('fact_quantity')) ?>" class="form-control form-control-sm text-end line-qty" value="<?= e($qty) ?>" required></td>
        <?php else: ?>
            <td><input type="number" step="0.001" min="0.001" name="<?= e($name('quantity')) ?>" class="form-control form-control-sm text-end line-qty" value="<?= e($qty) ?>" required></td>
            <td><input type="number" step="0.01" min="0" name="<?= e($name('price')) ?>" class="form-control form-control-sm text-end line-price" value="<?= e($val('price')) ?>"></td>
            <td><input type="number" step="0.01" min="0" max="100" name="<?= e($name('vat_rate')) ?>" class="form-control form-control-sm text-end line-vat" value="<?= e($val('vat_rate', $line['vat_rate_default'] ?? 20)) ?>"></td>
            <td class="text-end line-amount small">0.00</td>
        <?php endif; ?>
        <td><button type="button" class="btn btn-sm btn-outline-danger line-del" title="Удалить строку">×</button></td>
    </tr>
    <?php
    return (string) ob_get_clean();
}
