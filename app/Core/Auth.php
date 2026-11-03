<?php

declare(strict_types=1);

namespace Uchet\Core;

/**
 * Аутентификация и RBAC.
 * - пароли: password_hash(PASSWORD_ARGON2ID) / password_verify(), авто-рехеш при смене параметров;
 * - сессия хранит user_id, при каждом запросе пользователь загружается вместе с ролью (кешируется);
 * - права: can('documents.post') — ресурс.действие, в роли JSON вида
 *   {"documents": ["create","post",...], "catalogs": ["read"], "*": ["*"]}.
 */
final class Auth
{
    private const SESSION_KEY = 'user_id';

    private static ?array $userCache = null;

    private static bool $userLoaded = false;

    /** @return bool — true при верном логине/пароле */
    public static function attempt(string $login, string $password): bool
    {
        $row = self::findActiveUserByLogin($login);
        if ($row === null) {
            return false;
        }
        if (!password_verify($password, (string) $row['password_hash'])) {
            return false;
        }

        $hash = (string) $row['password_hash'];
        if (password_needs_rehash($hash, PASSWORD_ARGON2ID)) {
            $newHash = password_hash($password, PASSWORD_ARGON2ID);
            Database::connection()
                ->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$newHash, $row['id']]);
        }

        Database::connection()
            ->prepare('UPDATE users SET last_login_at = NOW(), updated_at = NOW() WHERE id = ?')
            ->execute([$row['id']]);

        self::loginRow($row);

        return true;
    }

    public static function loginById(int $id): bool
    {
        $row = self::fetchUserRow('u.id = ?', [$id]);
        if ($row === null) {
            return false;
        }
        self::loginRow($row);

        return true;
    }

    private static function findActiveUserByLogin(string $login): ?array
    {
        return self::fetchUserRow('u.login = ?', [$login]);
    }

    /** @param array<int, mixed> $params */
    private static function fetchUserRow(string $condition, array $params): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.*, r.code AS role_code, r.permissions AS role_permissions
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE ' . $condition . ' AND u.is_active = 1 AND u.deleted_at IS NULL AND r.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    private static function loginRow(array $row): void
    {
        Session::regenerate(); // защита от fixation
        Session::set(self::SESSION_KEY, (int) $row['id']);
        self::$userCache = $row;
        self::$userLoaded = true;
    }

    public static function logout(): void
    {
        Session::forget(self::SESSION_KEY);
        self::$userCache = null;
        self::$userLoaded = false;
        Session::regenerate();
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    public static function id(): ?int
    {
        self::loadUser();

        return isset(self::$userCache['id']) ? (int) self::$userCache['id'] : null;
    }

    /** @return array|null — строка пользователя + role_code + role_permissions */
    public static function user(): ?array
    {
        self::loadUser();

        return self::$userCache;
    }

    public static function roleCode(): ?string
    {
        $user = self::user();

        return $user['role_code'] ?? null;
    }

    private static function loadUser(): void
    {
        if (self::$userLoaded) {
            return;
        }
        self::$userLoaded = true;
        $id = Session::get(self::SESSION_KEY);
        if (!is_numeric($id)) {
            return;
        }
        self::loginById((int) $id);
        // loginById выставляет userLoaded=false? нет — он зовёт loginRow, но после него сбросим флаг ниже
        self::$userLoaded = true;
    }

    /**
     * Проверка права вида "resource.action" (напр. "documents.post") по JSON-разрешениям роли.
     * Поддерживаются wildcard: "*" как ресурс и как действие.
     */
    public static function can(string $permission): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }
        $permissions = json_decode((string) ($user['role_permissions'] ?? ''), true);
        if (!is_array($permissions)) {
            return false;
        }
        [$resource, $action] = array_pad(explode('.', $permission, 2), 2, '*');
        foreach ([$resource, '*'] as $res) {
            $actions = $permissions[$res] ?? null;
            if (is_array($actions) && (in_array('*', $actions, true) || in_array($action, $actions, true))) {
                return true;
            }
        }

        return false;
    }

    /** @throws ForbiddenException */
    public static function requirePermission(string $permission): void
    {
        if (!self::can($permission)) {
            throw new ForbiddenException('Недостаточно прав: требуется "' . $permission . '"');
        }
    }
}
