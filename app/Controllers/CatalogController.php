<?php

declare(strict_types=1);

namespace Uchet\Controllers;

use Uchet\Core\Controller;
use Uchet\Core\NotFoundException;
use Uchet\Core\Request;
use Uchet\Core\Session;
use Uchet\Core\Repository;
use Uchet\Services\AuditService;

/**
 * Базовый CRUD-контроллер справочника: список (поиск/пагинация/сортировка),
 * создание, редактирование, мягкое удаление. Представления — универсальные
 * (catalogs/index, catalogs/form), наполняются конфигами из наследника.
 */
abstract class CatalogController extends Controller
{
    abstract protected function repo(): Repository;

    abstract protected function titlePlural(): string;

    /** базовое имя маршрута, напр. 'catalogs.nomenclature' */
    abstract protected function routeBase(): string;

    /** колонки списка: [['key' => 'article', 'label' => 'Артикул', 'sortable' => true], ...] */
    abstract protected function columns(): array;

    /** поля формы: ['name','label','type'(text|number|select|checkbox|textarea|email),'options'=>[id=>label],'required','step','default'] */
    abstract protected function fields(): array;

    /** правила валидации; $excludeId — id записи при редактировании (для unique) */
    abstract protected function rules(?int $excludeId = null): array;

    /** колонка поиска по q (LIKE) */
    protected function searchColumn(): string
    {
        return 'name';
    }

    public function index(): void
    {
        $this->authorize('catalogs.read');
        $q = Request::search();
        $where = [];
        if ($q !== '') {
            $where[] = [$this->searchColumn(), 'LIKE', '%' . $q . '%'];
        }
        $sortable = array_column(array_filter($this->columns(), static fn ($c) => !empty($c['sortable'])), 'key');
        $sort = Request::sort($sortable[0] ?? 'id');
        if (!in_array($sort, $sortable, true)) {
            $sort = $sortable[0] ?? 'id';
        }
        $result = $this->repo()->paginate(Request::page(), Request::perPage(20), $where, $sort, Request::order() === 'DESC' ? 'DESC' : 'ASC');

        $this->render('catalogs.index', [
            'title' => $this->titlePlural(),
            'routeBase' => $this->routeBase(),
            'columns' => $this->columns(),
            'rows' => array_map(fn ($m) => $m->toArray(), $result['data']),
            'pagination' => $result,
            'q' => $q,
            'canCreate' => \Uchet\Core\Auth::can('catalogs.create'),
            'canDelete' => \Uchet\Core\Auth::can('catalogs.delete'),
        ]);
    }

    public function create(): void
    {
        $this->authorize('catalogs.create');
        $this->render('catalogs.form', [
            'title' => 'Новая запись: ' . $this->titlePlural(),
            'routeBase' => $this->routeBase(),
            'mode' => 'create',
            'fields' => $this->fields(),
            'values' => [],
        ]);
    }

    public function store(): void
    {
        $this->authorize('catalogs.create');
        $this->csrf();
        $v = $this->validate($this->rules());
        $header = $this->repo()->create($v);
        AuditService::log($this->repo()->tableName(), (int) $header->id, 'create', null, $v);
        Session::flash('success', 'Запись создана');
        $this->redirect(url($this->routeBase() . '.index'));
    }

    public function edit(string $id): void
    {
        $this->authorize('catalogs.update');
        $model = $this->repo()->find((int) $id);
        if ($model === null) {
            throw new NotFoundException('Запись не найдена');
        }
        $this->render('catalogs.form', [
            'title' => 'Редактирование: ' . $this->titlePlural(),
            'routeBase' => $this->routeBase(),
            'mode' => 'edit',
            'id' => (int) $id,
            'fields' => $this->fields(),
            'values' => $model->toArray(),
        ]);
    }

    public function update(string $id): void
    {
        $this->authorize('catalogs.update');
        $this->csrf();
        $v = $this->validate($this->rules((int) $id));
        $old = $this->repo()->find((int) $id)?->toArray();
        $this->repo()->update((int) $id, $v);
        AuditService::log($this->repo()->tableName(), (int) $id, 'update', $old, $v);
        Session::flash('success', 'Запись сохранена');
        $this->redirect(url($this->routeBase() . '.index'));
    }

    public function destroy(string $id): void
    {
        $this->authorize('catalogs.delete');
        $this->csrf();
        $old = $this->repo()->find((int) $id)?->toArray();
        $this->repo()->delete((int) $id);
        AuditService::log($this->repo()->tableName(), (int) $id, 'delete', $old, null);
        Session::flash('success', 'Запись удалена (soft delete)');
        $this->redirect(url($this->routeBase() . '.index'));
    }
}
