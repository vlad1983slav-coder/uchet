<?php

declare(strict_types=1);

namespace Uchet\Controllers;

use Uchet\Core\Auth;
use Uchet\Core\Controller;
use Uchet\Core\HttpException;
use Uchet\Core\NotFoundException;
use Uchet\Core\Request;
use Uchet\Core\Session;
use Uchet\Core\Validator;
use Uchet\Core\View;
use Uchet\Repositories\AuditRepository;
use Uchet\Repositories\CounterpartyRepository;
use Uchet\Repositories\DocumentRepository;
use Uchet\Repositories\MovementRepository;
use Uchet\Repositories\NomenclatureRepository;
use Uchet\Repositories\WarehouseRepository;
use Uchet\Services\AuditService;
use Uchet\Services\DocumentService;

/**
 * Базовый контроллер документа (шапка + ТЧ): список с фильтрами, создание, редактирование,
 * просмотр, проведение/распроведение, удаление черновика, печатная форма.
 * Наследники задают тип документа и маршруты — вся логика общая.
 */
abstract class BaseDocumentController extends Controller
{
    abstract protected function docType(): string;

    abstract protected function titleSingular(): string;

    abstract protected function titlePlural(): string;

    abstract protected function routeBase(): string;

    /** 'purchase' | 'retail' | 'cost' — какую цену подставлять в новую строку ТЧ */
    protected function priceMode(): string
    {
        return 'purchase';
    }

    private ?DocumentService $service = null;

    protected function service(): DocumentService
    {
        return $this->service ??= DocumentService::make();
    }

    protected function repo(): DocumentRepository
    {
        return $this->service()->repoFor($this->docType());
    }

    // ------------------------------------------------------------- правила шапки (переопределяются)

    protected function headerRules(): array
    {
        return [
            'doc_date' => 'required|date',
            'comment' => 'nullable|string|max:1000',
        ];
    }

    /** дополнительные правила шапки (склад, контрагент...) — в наследнике */
    protected function extraHeaderRules(): array
    {
        return [];
    }

    /** validated → колонки шапки */
    protected function headerData(array $v): array
    {
        return [
            'doc_date' => $this->normalizeDateTime((string) $v['doc_date']),
            'comment' => $v['comment'] ?? null,
        ];
    }

    protected function extraHeaderData(array $v): array
    {
        return [];
    }

    /** правила одной строки ТЧ */
    protected function lineRules(): array
    {
        return [
            'nomenclature_id' => 'required|integer|exists:catalogs_nomenclature,id',
            'quantity' => 'required|numeric|min:0.001',
            'price' => 'nullable|numeric|min:0',
            'vat_rate' => 'nullable|numeric|min:0|max:100',
        ];
    }

    /** дополнительные поля строки (ИНВ: book/fact) */
    protected function extraLineData(array $row, array $validated): array
    {
        return [];
    }

    protected function normalizeDateTime(string $value): string
    {
        $ts = strtotime($value);

        return $ts === false ? date('Y-m-d H:i:s') : date('Y-m-d H:i:s', $ts);
    }

    // ------------------------------------------------------------- список

    public function index(): void
    {
        $this->authorize('documents.read');
        $filters = array_filter([
            'q' => Request::search() !== '' ? Request::search() : null,
            'status' => Request::get('status') ?: null,
            'warehouse_id' => Request::get('warehouse_id') ?: null,
            'counterparty_id' => Request::get('counterparty_id') ?: null,
            'date_from' => Request::get('date_from') ?: null,
            'date_to' => Request::get('date_to') ?: null,
        ], static fn ($v) => $v !== null && $v !== '');
        if ($this->docType() === DocumentService::TYPE_RETURNS) {
            $filters['direction'] = Request::get('direction') ?: null;
        }

        $repo = $this->repo();
        $result = $repo->listDocuments(
            $filters,
            Request::page(),
            Request::perPage(20),
            Request::sort('doc_date') ?? 'doc_date',
            Request::order()
        );

        $this->render('documents.list', [
            'title' => $this->titlePlural(),
            'type' => $this->docType(),
            'routeBase' => $this->routeBase(),
            'docs' => $result['data'],
            'pagination' => $result,
            'filters' => $filters,
            'warehouses' => array_map(fn ($m) => $m->toArray(), (new WarehouseRepository())->all([], 'code')),
            'counterparties' => array_map(fn ($m) => $m->toArray(), (new CounterpartyRepository())->all([], 'name')),
        ]);
    }

    // ------------------------------------------------------------- создание

    public function create(): void
    {
        $this->authorize('documents.create');
        $this->render('documents.form', $this->formData(null));
    }

    public function store(): void
    {
        $this->authorize('documents.create');
        $this->csrf();
        $v = $this->validate($this->headerRules() + $this->extraHeaderRules());
        $lines = $this->extractLines((array) Request::post('lines', []));
        $data = $this->headerData($v) + $this->extraHeaderData($v);
        $id = $this->service()->createDocument($this->docType(), $data, $lines);
        Session::flash('success', 'Документ создан (статус: черновик). Проведите его кнопкой «Провести».');
        $this->redirect(url($this->routeBase() . '.show', ['id' => $id]));
    }

    // ------------------------------------------------------------- просмотр

    public function show(string $id): void
    {
        $this->authorize('documents.read');
        $repo = $this->repo();
        $found = $repo->findWithLines((int) $id);
        if ($found === null) {
            throw new NotFoundException('Документ не найден');
        }
        ['header' => $header, 'lines' => $lines] = $found;

        $movements = (new MovementRepository())->byDocument($this->docType(), (int) $id);
        $audit = (new AuditRepository())->forEntity($repo->tableName(), $id);

        $this->render('documents.show', [
            'title' => $this->titleSingular() . ' ' . $header->number,
            'type' => $this->docType(),
            'routeBase' => $this->routeBase(),
            'header' => $header->toArray(),
            'lines' => $this->linesWithNames($lines),
            'movements' => $this->movementsWithNames($movements),
            'audit' => $audit,
            'warehouses' => array_map(fn ($m) => $m->toArray(), (new WarehouseRepository())->all([], 'code')),
            'counterparties' => array_map(fn ($m) => $m->toArray(), (new CounterpartyRepository())->all([], 'name')),
            'canEdit' => $header->status !== 'posted' && Auth::can('documents.update'),
            'canPost' => $header->status !== 'posted' && Auth::can('documents.post'),
            'canUnpost' => $header->status === 'posted' && Auth::can('documents.unpost'),
            'canDelete' => $header->status !== 'posted' && Auth::can('documents.delete'),
        ]);
    }

    // ------------------------------------------------------------- редактирование

    public function edit(string $id): void
    {
        $this->authorize('documents.update');
        $found = $this->repo()->findWithLines((int) $id);
        if ($found === null) {
            throw new NotFoundException('Документ не найден');
        }
        if ($found['header']->status === 'posted') {
            Session::flash('warning', 'Документ проведён — редактирование запрещено. Сначала распроведите его.');
            $this->redirect(url($this->routeBase() . '.show', ['id' => $id]));
        }
        $this->render('documents.form', $this->formData($found['header'], $found['lines']));
    }

    public function update(string $id): void
    {
        $this->authorize('documents.update');
        $this->csrf();
        $v = $this->validate($this->headerRules() + $this->extraHeaderRules());
        $lines = $this->extractLines((array) Request::post('lines', []));
        $data = $this->headerData($v) + $this->extraHeaderData($v);
        $this->service()->updateDocument($this->docType(), (int) $id, $data, $lines);
        Session::flash('success', 'Документ сохранён');
        $this->redirect(url($this->routeBase() . '.show', ['id' => $id]));
    }

    // ------------------------------------------------------------- проведение / распроведение / удаление

    public function post(string $id): void
    {
        $this->authorize('documents.post');
        $this->csrf();
        try {
            $this->service()->post($this->docType(), (int) $id);
            $message = 'Документ проведён';
            if (Request::wantsJson()) {
                $this->json(['ok' => true, 'message' => $message, 'status' => 'posted']);
            }
            Session::flash('success', $message);
        } catch (HttpException $e) {
            if (Request::wantsJson()) {
                $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }
            Session::flash('error', $e->getMessage());
        }
        $this->redirect(url($this->routeBase() . '.show', ['id' => $id]));
    }

    public function unpost(string $id): void
    {
        $this->authorize('documents.unpost');
        $this->csrf();
        try {
            $this->service()->unpost($this->docType(), (int) $id);
            $message = 'Документ распроведен (статус: черновик)';
            if (Request::wantsJson()) {
                $this->json(['ok' => true, 'message' => $message, 'status' => 'draft']);
            }
            Session::flash('success', $message);
        } catch (HttpException $e) {
            if (Request::wantsJson()) {
                $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }
            Session::flash('error', $e->getMessage());
        }
        $this->redirect(url($this->routeBase() . '.show', ['id' => $id]));
    }

    public function destroy(string $id): void
    {
        $this->authorize('documents.delete');
        $this->csrf();
        $repo = $this->repo();
        $header = $repo->find((int) $id);
        if ($header === null) {
            throw new NotFoundException('Документ не найден');
        }
        if ($header->status === 'posted') {
            Session::flash('error', 'Нельзя удалить проведённый документ — сначала распроведите его');
            $this->redirect(url($this->routeBase() . '.show', ['id' => $id]));
        }
        $repo->delete((int) $id); // soft delete шапки
        $repo->execute('UPDATE ' . $repo->linesTableName() . ' SET deleted_at = NOW() WHERE document_id = ?', [(int) $id]);
        AuditService::log($repo->tableName(), (int) $id, 'delete', $header->toArray(), null);
        Session::flash('success', 'Документ удалён (помечен deleted_at)');
        $this->redirect(url($this->routeBase() . '.index'));
    }

    // ------------------------------------------------------------- печатная форма

    public function print(string $id): void
    {
        $this->authorize('documents.read');
        $repo = $this->repo();
        $found = $repo->findWithLines((int) $id);
        if ($found === null) {
            throw new NotFoundException('Документ не найден');
        }
        $html = View::render('print.document', [
            'title' => $this->titleSingular() . ' № ' . $found['header']->number,
            'type' => $this->docType(),
            'header' => $found['header']->toArray(),
            'lines' => $this->linesWithNames($found['lines']),
            'warehouses' => array_column((new WarehouseRepository())->allWithMol(), null, 'id'),
            'counterparties' => array_column((new CounterpartyRepository())->all(), null, 'id'),
            'orgName' => \Uchet\Services\SettingsService::get('org_name', 'Организация'),
            'orgInn' => \Uchet\Services\SettingsService::get('org_inn', ''),
        ]);

        // PDF через dompdf, если библиотека установлена (composer require dompdf/dompdf)
        if (class_exists(\Dompdf\Dompdf::class)) {
            try {
                $dompdf = new \Dompdf\Dompdf();
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                \Uchet\Core\Response::download($dompdf->output(), $found['header']->number . '.pdf', 'application/pdf');
            } catch (\Throwable) {
                // если PDF не получился — отдадим HTML для печати
            }
        }
        \Uchet\Core\Response::html($html);
    }

    // ------------------------------------------------------------- вспомогательное

    /** @param array<string, mixed>|null $header */
    protected function formData(?object $header, ?array $lines = null): array
    {
        if ($lines === null && $header !== null) {
            $lines = $this->repo()->lines((int) $header->id);
        }

        return [
            'title' => $header === null ? 'Новый документ: ' . $this->titleSingular() : $this->titleSingular() . ' ' . $header->number,
            'type' => $this->docType(),
            'routeBase' => $this->routeBase(),
            'mode' => $header === null ? 'create' : 'edit',
            'header' => $header?->toArray() ?? ['doc_date' => date('Y-m-d\TH:i')],
            'lines' => $this->linesWithNames($lines ?? []),
            'status' => $header->status ?? 'draft',
            'priceMode' => $this->priceMode(),
            'warehouses' => array_map(fn ($m) => $m->toArray(), (new WarehouseRepository())->all([], 'code')),
            'counterparties' => array_map(fn ($m) => $m->toArray(), (new CounterpartyRepository())->all([], 'name')),
        ];
    }

    /** @param object[] $lines @return array<int, array<string, mixed>> — строки + article/name/unit/vat */
    protected function linesWithNames(array $lines): array
    {
        $nomRepo = new NomenclatureRepository();
        $ids = array_values(array_unique(array_map(static fn ($l) => (int) $l->nomenclature_id, $lines)));
        $names = [];
        if ($ids !== []) {
            $rows = $nomRepo->query(
                'SELECT n.id, n.article, n.name, n.vat_rate, n.purchase_price, u.name AS unit_name
                 FROM catalogs_nomenclature n LEFT JOIN catalogs_units u ON u.id = n.unit_id
                 WHERE n.id IN (' . implode(',', array_map('intval', $ids)) . ')'
            );
            $names = array_column($rows, null, 'id');
        }
        $result = [];
        foreach ($lines as $line) {
            $row = $line->toArray();
            $nom = $names[(int) $line->nomenclature_id] ?? null;
            $row['article'] = $nom['article'] ?? '';
            $row['nom_name'] = $nom['name'] ?? '';
            $row['unit_name'] = $nom['unit_name'] ?? '';
            $row['vat_rate_default'] = $nom['vat_rate'] ?? 20;
            $row['purchase_price'] = $nom['purchase_price'] ?? 0;
            $result[] = $row;
        }

        return $result;
    }

    /** @param array<int, array<string, mixed>> $movements */
    protected function movementsWithNames(array $movements): array
    {
        $nomRepo = new NomenclatureRepository();
        $ids = array_values(array_unique(array_map(static fn ($m) => (int) $m['nomenclature_id'], $movements)));
        $names = [];
        if ($ids !== []) {
            $names = array_column(
                $nomRepo->query('SELECT id, article, name FROM catalogs_nomenclature WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')'),
                null,
                'id'
            );
        }
        foreach ($movements as &$mv) {
            $nom = $names[(int) $mv['nomenclature_id']] ?? null;
            $mv['article'] = $nom['article'] ?? '';
            $mv['nom_name'] = $nom['name'] ?? '';
        }

        return $movements;
    }

    /**
     * Валидация и нормализация строк ТЧ из POST.
     *
     * @param array<int, mixed> $raw
     * @return array<int, array<string, mixed>>
     */
    protected function extractLines(array $raw): array
    {
        $out = [];
        $errors = [];
        foreach ($raw as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            $nomId = (int) ($row['nomenclature_id'] ?? 0);
            $qty = $row['quantity'] ?? null;
            if ($nomId === 0 && ($qty === null || $qty === '')) {
                continue; // пустая строка — пропускаем
            }
            $validator = Validator::make($row, $this->lineRules());
            if (!$validator->validate()) {
                foreach ($validator->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $errors['lines.' . $i . '.' . $field][] = 'Строка ' . ((int) $i + 1) . ': ' . $message;
                    }
                }
                continue;
            }
            $vd = $validator->validated();
            $line = [
                'nomenclature_id' => (int) $vd['nomenclature_id'],
                'quantity' => (float) $vd['quantity'],
                'price' => (float) ($vd['price'] ?? 0),
                'vat_rate' => (float) ($vd['vat_rate'] ?? 20),
            ];
            if ($this->docType() === DocumentService::TYPE_INVENTORIES) {
                $line['book_quantity'] = (float) ($vd['book_quantity'] ?? $line['quantity']);
                $line['fact_quantity'] = (float) ($vd['fact_quantity'] ?? $line['quantity']);
                $line['party_id'] = isset($vd['party_id']) && (int) $vd['party_id'] > 0 ? (int) $vd['party_id'] : null;
            }
            $out[] = $line + $this->extraLineData($row, $vd);
        }
        if ($errors !== []) {
            Session::flash('errors', $errors);
            Session::flashInput(Request::all());
            $this->back();
        }
        if ($out === []) {
            Session::flash('errors', ['lines' => ['Добавьте хотя бы одну строку в табличную часть']]);
            Session::flashInput(Request::all());
            $this->back();
        }

        return $out;
    }
}
