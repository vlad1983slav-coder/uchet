<?php

declare(strict_types=1);

namespace Uchet\Controllers;

use Uchet\Core\Controller;
use Uchet\Core\NotFoundException;
use Uchet\Core\Request;
use Uchet\Core\Session;
use Uchet\Repositories\RoleRepository;
use Uchet\Repositories\UserRepository;
use Uchet\Services\AuditService;

/** Управление пользователями и ролями — только для admin (users.*). */
final class UserController extends Controller
{
    public function index(): void
    {
        $this->authorize('users.read');
        $users = (new UserRepository())->allWithRoles();
        $this->render('users.index', [
            'title' => 'Пользователи',
            'users' => $users,
            'roles' => (new RoleRepository())->all([], 'code'),
        ]);
    }

    public function create(): void
    {
        $this->authorize('users.create');
        $this->render('users.form', [
            'title' => 'Новый пользователь',
            'mode' => 'create',
            'values' => [],
            'roles' => (new RoleRepository())->all([], 'code'),
        ]);
    }

    public function store(): void
    {
        $this->authorize('users.create');
        $this->csrf();
        $v = $this->validate([
            'login' => 'required|string|max:64|unique:users,login,NULL,id',
            'email' => 'nullable|email|max:255',
            'password' => 'required|string|min:6|max:72',
            'full_name' => 'required|string|max:255',
            'role_id' => 'required|integer|exists:roles,id',
            'is_active' => 'nullable|boolean',
        ]);
        $user = (new UserRepository())->create([
            'login' => $v['login'],
            'email' => $v['email'] ?? null,
            'password_hash' => password_hash($v['password'], PASSWORD_ARGON2ID),
            'full_name' => $v['full_name'],
            'role_id' => (int) $v['role_id'],
            'is_active' => isset($v['is_active']) ? 1 : 0,
        ]);
        AuditService::log('users', (int) $user->id, 'create', null, ['login' => $v['login'], 'role_id' => $v['role_id']]);
        Session::flash('success', 'Пользователь создан');
        $this->redirect(url('users.index'));
    }

    public function edit(string $id): void
    {
        $this->authorize('users.update');
        $user = (new UserRepository())->find((int) $id);
        if ($user === null) {
            throw new NotFoundException('Пользователь не найден');
        }
        $this->render('users.form', [
            'title' => 'Пользователь: ' . $user->login,
            'mode' => 'edit',
            'id' => (int) $id,
            'values' => $user->toArray(),
            'roles' => (new RoleRepository())->all([], 'code'),
        ]);
    }

    public function update(string $id): void
    {
        $this->authorize('users.update');
        $this->csrf();
        $rules = [
            'login' => 'required|string|max:64|unique:users,login,' . (int) $id . ',id',
            'email' => 'nullable|email|max:255',
            'full_name' => 'required|string|max:255',
            'role_id' => 'required|integer|exists:roles,id',
            'is_active' => 'nullable|boolean',
            'password' => 'nullable|string|min:6|max:72',
        ];
        $v = $this->validate($rules);
        $data = [
            'login' => $v['login'],
            'email' => $v['email'] ?? null,
            'full_name' => $v['full_name'],
            'role_id' => (int) $v['role_id'],
            'is_active' => isset($v['is_active']) ? 1 : 0,
        ];
        if (!empty($v['password'])) {
            $data['password_hash'] = password_hash($v['password'], PASSWORD_ARGON2ID);
        }
        $old = (new UserRepository())->find((int) $id)?->toArray();
        (new UserRepository())->update((int) $id, $data);
        AuditService::log('users', (int) $id, 'update', $old, ['login' => $v['login'], 'role_id' => $v['role_id'], 'password_changed' => !empty($v['password'])]);
        Session::flash('success', 'Пользователь сохранён');
        $this->redirect(url('users.index'));
    }

    public function destroy(string $id): void
    {
        $this->authorize('users.delete');
        $this->csrf();
        if ((int) $id === (int) \Uchet\Core\Auth::id()) {
            Session::flash('error', 'Нельзя удалить самого себя');
            $this->redirect(url('users.index'));
        }
        $old = (new UserRepository())->find((int) $id)?->toArray();
        (new UserRepository())->delete((int) $id);
        AuditService::log('users', (int) $id, 'delete', $old, null);
        Session::flash('success', 'Пользователь деактивирован (soft delete)');
        $this->redirect(url('users.index'));
    }
}
