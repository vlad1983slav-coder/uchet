/* ==========================================================================
   UCHET — vanilla JS: динамическая табличная часть, автокомплит артикула,
   живые итоги, AJAX проведение/распроведение/удаление, автофильтры.
   ========================================================================== */
(function () {
    'use strict';

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) return meta.content;
        var input = document.querySelector('input[name="_csrf"]');
        return input ? input.value : '';
    }

    function fmt(n, digits) {
        return Number(n || 0).toLocaleString('ru-RU', { minimumFractionDigits: digits, maximumFractionDigits: digits });
    }

    /* ---------- AJAX-кнопки: Провести / Распровести / Удалить ---------- */
    document.querySelectorAll('[data-ajax-action]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var action = btn.getAttribute('data-ajax-action');
            var url = btn.getAttribute('data-url');
            var redirect = btn.getAttribute('data-redirect');
            var label = { post: 'Провести документ?', unpost: 'Распровести документ?', delete: 'Удалить черновик?' }[action];
            if (label && !window.confirm(label)) return;
            btn.disabled = true;
            fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfToken(),
                    'Accept': 'application/json'
                }
            }).then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); })
                .then(function (res) {
                    if (res.body && res.body.ok) {
                        if (redirect) { window.location.href = redirect; return; }
                        window.location.reload();
                    } else {
                        alert((res.body && res.body.message) || 'Ошибка');
                        btn.disabled = false;
                    }
                })
                .catch(function () { btn.disabled = false; alert('Сетевая ошибка'); });
        });
    });

    /* ---------- автофильтры списков ---------- */
    var filters = document.getElementById('doc-filters');
    if (filters) {
        filters.querySelectorAll('select').forEach(function (sel) {
            sel.addEventListener('change', function () { filters.submit(); });
        });
    }

    /* ---------- табличная часть документа ---------- */
    var form = document.getElementById('doc-form');
    if (!form) return;

    var priceMode = form.getAttribute('data-price-mode') || 'purchase';
    var docType = form.getAttribute('data-doc-type') || '';
    var isInv = docType === 'inventories';
    var tbody = document.getElementById('lines-body');
    var template = document.getElementById('line-template');

    function nextIndex() {
        var max = -1;
        tbody.querySelectorAll('tr.line-row').forEach(function (tr) {
            tr.querySelectorAll('input,select').forEach(function (inp) {
                var m = /\[(\d+)\]/.exec(inp.name || '');
                if (m) max = Math.max(max, parseInt(m[1], 10));
            });
        });
        return max + 1;
    }

    function renumber() {
        tbody.querySelectorAll('tr.line-row').forEach(function (tr, i) {
            tr.querySelector('.line-no').textContent = String(i + 1);
            tr.querySelectorAll('input').forEach(function (inp) {
                inp.name = inp.name.replace(/\[\d+\]/, '[' + i + ']');
            });
        });
        recalcTotals();
    }

    function recalcTotals() {
        var totalQty = 0, totalAmount = 0, totalBook = 0, totalFact = 0;
        tbody.querySelectorAll('tr.line-row').forEach(function (tr) {
            var qty = parseFloat(tr.querySelector('.line-qty') ? tr.querySelector('.line-qty').value : 0) || 0;
            if (isInv) {
                totalFact += qty;
                var book = parseFloat(tr.querySelector('.line-book') ? tr.querySelector('.line-book').value : 0) || 0;
                totalBook += book;
                var amountCell = null;
            } else {
                totalQty += qty;
                var price = parseFloat(tr.querySelector('.line-price') ? tr.querySelector('.line-price').value : 0) || 0;
                var amount = qty * price;
                totalAmount += amount;
                var cell = tr.querySelector('.line-amount');
                if (cell) cell.textContent = fmt(amount, 2);
            }
        });
        var tq = document.getElementById('total-qty');
        if (tq) tq.textContent = fmt(totalQty, 3);
        var ta = document.getElementById('total-amount');
        if (ta) ta.textContent = fmt(totalAmount, 2);
        var tb = document.getElementById('total-book');
        if (tb) tb.textContent = fmt(totalBook, 3);
        var tf = document.getElementById('total-fact');
        if (tf) tf.textContent = fmt(totalFact, 3);
    }

    function addLine() {
        if (!template) return;
        var html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex()));
        var holder = document.createElement('tbody');
        holder.innerHTML = html;
        var tr = holder.querySelector('tr.line-row');
        if (!tr) return;
        tbody.appendChild(tr);
        renumber();
        var articleInput = tr.querySelector('.line-article');
        if (articleInput) articleInput.focus();
    }

    document.getElementById('add-line').addEventListener('click', addLine);

    tbody.addEventListener('click', function (e) {
        if (e.target.closest('.line-del')) {
            var tr = e.target.closest('tr.line-row');
            if (tbody.querySelectorAll('tr.line-row').length <= 1 && !isInv) {
                // последнюю строку не удаляем, просто очищаем
                tr.querySelectorAll('input').forEach(function (inp) { inp.value = ''; });
                tr.querySelector('.line-nom-id').value = '';
                tr.querySelector('.line-name').textContent = '';
                tr.querySelector('.line-unit').textContent = '';
                tr.querySelector('.line-amount') && (tr.querySelector('.line-amount').textContent = '0.00');
            } else {
                tr.remove();
            }
            renumber();
        }
    });

    tbody.addEventListener('input', function (e) {
        if (e.target.classList.contains('line-qty') || e.target.classList.contains('line-price')) recalcTotals();
    });

    /* ---------- автокомплит по артикулу ---------- */
    var activeAc = null;

    function closeAc() {
        document.querySelectorAll('.autocomplete-list').forEach(function (el) { el.classList.add('d-none'); el.innerHTML = ''; });
        activeAc = null;
    }

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.autocomplete-list') && !e.target.classList.contains('line-article')) closeAc();
    });

    function pickItem(tr, item) {
        tr.querySelector('.line-nom-id').value = item.id;
        var art = tr.querySelector('.line-article');
        art.value = item.article;
        tr.querySelector('.line-name').textContent = item.name;
        tr.querySelector('.line-unit').textContent = item.unit_name || '';
        var vat = tr.querySelector('.line-vat');
        if (vat) vat.value = item.vat_rate;
        var price = tr.querySelector('.line-price');
        if (price) {
            var p = priceMode === 'retail' ? (item.retail_price || item.purchase_price)
                : (priceMode === 'purchase' || priceMode === 'cost') ? item.purchase_price : item.purchase_price;
            price.value = Number(p).toFixed(2);
        }
        var qty = tr.querySelector('.line-qty');
        if (qty && !qty.value) qty.value = '1';
        recalcTotals();
    }

    tbody.addEventListener('input', function (e) {
        var input = e.target.closest('.line-article');
        if (!input) return;
        var tr = input.closest('tr.line-row');
        var list = tr.querySelector('.autocomplete-list');
        var q = input.value.trim();
        if (q.length < 1) { closeAc(); return; }
        clearTimeout(input._t);
        input._t = setTimeout(function () {
            fetch('/ajax/nomenclature?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.ok || !res.items || !res.items.length) { closeAc(); return; }
                    activeAc = list;
                    list.innerHTML = '';
                    res.items.forEach(function (item) {
                        var div = document.createElement('div');
                        div.className = 'ac-item';
                        div.innerHTML = '<span class="ac-article"></span><span class="ac-name"></span><span class="ac-price"></span>';
                        div.querySelector('.ac-article').textContent = item.article;
                        div.querySelector('.ac-name').textContent = ' ' + item.name;
                        div.querySelector('.ac-price').textContent = fmt(item.purchase_price, 2) + ' / ' + fmt(item.retail_price, 2);
                        div.addEventListener('mousedown', function (ev) {
                            ev.preventDefault();
                            pickItem(tr, item);
                            closeAc();
                        });
                        list.appendChild(div);
                    });
                    list.classList.remove('d-none');
                })
                .catch(function () {});
        }, 250);
    });

    /* при смене направления возврата — меняем режим цены */
    var dirSelect = document.getElementById('direction');
    if (dirSelect) {
        dirSelect.addEventListener('change', function () {
            form.setAttribute('data-price-mode', dirSelect.value === 'from_customer' ? 'retail' : 'purchase');
            priceMode = form.getAttribute('data-price-mode');
        });
    }

    /* Enter в артикуле = выбрать первую подсказку */
    tbody.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.classList.contains('line-article')) {
            e.preventDefault();
            var tr = e.target.closest('tr.line-row');
            var list = tr.querySelector('.autocomplete-list');
            var first = list && list.querySelector('.ac-item');
            if (first && !list.classList.contains('d-none')) {
                // подсказки хранятся в DOM без данных — выберем по индексу через повторный запрос
                first.dispatchEvent(new Event('mousedown'));
            } else {
                addLine();
            }
        }
    });

    renumber();
})();
