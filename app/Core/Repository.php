<?php

declare(strict_types=1);

namespace Uchet\Core;

/**
 * Базовый репозиторий — ЕДИНСТВЕННОЕ место, где пишется SQL.
 * Только PDO prepared statements; идентификаторы квотируются через Database::quoteIdentifier().
 *
 * Наследник:
 *   final class NomenclatureRepository extends Repository {
 *       public function __construct() { parent::__construct('catalogs_nomenclature', Nomenclature::class); }
 *   }
 *
 * $softDelete = false для регистров/настроек (таблицы без deleted_at).
 */
abstract class Repository
{
    /**
     * @param string $table Имя таблицы
     * @param string $modelClass Класс модели ('' — возвращать stdClass)
     * @param bool $softDelete Есть ли в таблице колонка deleted_at
     */
    public function __construct(
        protected readonly string $table,
        protected readonly string $modelClass = '',
        protected readonly bool $softDelete = true,
    ) {}

    protected function pdo(): \PDO
    {
        return Database::connection();
    }

    protected function q(string $identifier): string
    {
        return Database::quoteIdentifier($identifier);
    }

    protected function hydrate(array $row): object
    {
        if ($this->modelClass !== '' && class_exists($this->modelClass)) {
            $class = $this->modelClass;

            return $class::fromRow($row);
        }

        return (object) $row;
    }

    protected function deletedCondition(): string
    {
        return $this->softDelete ? ' AND deleted_at IS NULL' : '';
    }

    // ---------------------------------------------------------------- чтение

    public function find(int|string $id): ?object
    {
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM ' . $this->q($this->table) . ' WHERE id = ?' . $this->deletedCondition() . ' LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /** @throws NotFoundException */
    public function findOrFail(int|string $id): object
    {
        $model = $this->find($id);
        if ($model === null) {
            throw new NotFoundException("Запись {$this->table}#{$id} не найдена");
        }

        return $model;
    }

    public function findWithDeleted(int|string $id): ?object
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM ' . $this->q($this->table) . ' WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * Условия WHERE:
     *   ['warehouse_id' => 1, 'party_id' => null]        — равенство / IS NULL
     *   ['quantity' => [20.000, 10]]                     — IN (...)
     *   [['doc_date', '>=', '2026-01-01'], ...]          — кортеж [колонка, оператор, значение]
     *
     * @param array<int|string, mixed> $where
     * @return array{0: string, 1: array<int, mixed>} — [sql-фрагмент с AND..., параметры]
     */
    public function buildWhere(array $where): array
    {
        $sql = '';
        $params = [];
        foreach ($where as $key => $condition) {
            if (is_int($key) && is_array($condition)) {
                [$col, $op, $value] = array_pad($condition, 3, null);
                $colSql = $this->q((string) $col);
                $op = strtoupper(trim((string) $op));
                $allowedOps = ['=', '<>', '!=', '>', '>=', '<', '<=', 'LIKE'];
                if (!in_array($op, $allowedOps, true)) {
                    throw new \InvalidArgumentException("Недопустимый оператор WHERE: {$op}");
                }
                $sql .= " AND {$colSql} {$op} ?";
                $params[] = $value;
            } elseif (is_array($condition)) {
                // IN (...)
                $placeholders = implode(', ', array_fill(0, count($condition), '?'));
                $sql .= ' AND ' . $this->q((string) $key) . " IN ({$placeholders})";
                foreach ($condition as $v) {
                    $params[] = $v;
                }
            } elseif ($condition === null) {
                $sql .= ' AND ' . $this->q((string) $key) . ' IS NULL';
            } else {
                $sql .= ' AND ' . $this->q((string) $key) . ' = ?';
                $params[] = $condition;
            }
        }

        return [$sql, $params];
    }

    /**
     * @param array<int|string, mixed> $where
     * @return object[]
     */
    public function all(array $where = [], string $orderBy = 'id', string $orderDir = 'ASC', ?int $limit = null): array
    {
        [$whereSql, $params] = $this->buildWhere($where);
        $sql = 'SELECT * FROM ' . $this->q($this->table)
            . ' WHERE 1=1' . $this->deletedCondition() . $whereSql
            . ' ORDER BY ' . $this->q($orderBy) . ' ' . ($orderDir === 'DESC' ? 'DESC' : 'ASC');
        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, $limit);
        }
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);

        return array_map(fn (array $row) => $this->hydrate($row), $stmt->fetchAll());
    }

    /**
     * Пагинация для списков 1С.
     *
     * @param array<int|string, mixed> $where
     * @return array{data: object[], total: int, page: int, per_page: int, last_page: int}
     */
    public function paginate(
        int $page,
        int $perPage,
        array $where = [],
        string $orderBy = 'id',
        string $orderDir = 'ASC',
    ): array {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        [$whereSql, $params] = $this->buildWhere($where);
        $from = 'FROM ' . $this->q($this->table) . ' WHERE 1=1' . $this->deletedCondition() . $whereSql;

        $countStmt = $this->pdo()->prepare('SELECT COUNT(*) ' . $from);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = 'SELECT * ' . $from
            . ' ORDER BY ' . $this->q($orderBy) . ' ' . ($orderDir === 'DESC' ? 'DESC' : 'ASC')
            . ' LIMIT ? OFFSET ?';
        $stmt = $this->pdo()->prepare($sql);
        foreach ($params as $i => $value) {
            $stmt->bindValue($i + 1, $value);
        }
        $stmt->bindValue(count($params) + 1, $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, ($page - 1) * $perPage, \PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => array_map(fn (array $row) => $this->hydrate($row), $stmt->fetchAll()),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    // ---------------------------------------------------------------- запись

    /** @param array<string, mixed> $data @return object — созданная модель с id */
    public function create(array $data): object
    {
        $now = date('Y-m-d H:i:s');
        if ($this->softDelete || $this->hasColumn('created_at')) {
            $data['created_at'] ??= $now;
        }
        if ($this->hasColumn('updated_at')) {
            $data['updated_at'] ??= $now;
        }
        $columns = array_keys($data);
        $sql = 'INSERT INTO ' . $this->q($this->table)
            . ' (' . implode(', ', array_map(fn (string $c) => $this->q($c), $columns)) . ')'
            . ' VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(array_values($data));

        $id = (int) Database::lastInsertId();

        return $this->findWithDeleted($id) ?? $this->hydrate($data + ['id' => $id]);
    }

    /** @param array<string, mixed> $data */
    public function update(int|string $id, array $data): bool
    {
        unset($data['id'], $data['created_at'], $data['deleted_at']);
        if ($this->hasColumn('updated_at')) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        if ($data === []) {
            return true;
        }
        $sets = implode(', ', array_map(fn (string $c) => $this->q($c) . ' = ?', array_keys($data)));
        $sql = 'UPDATE ' . $this->q($this->table) . " SET {$sets} WHERE id = ?" . $this->deletedCondition();
        $stmt = $this->pdo()->prepare($sql);

        return $stmt->execute([...array_values($data), $id]);
    }

    /** Soft delete: deleted_at = NOW(). */
    public function delete(int|string $id): bool
    {
        if (!$this->softDelete) {
            return $this->forceDelete($id);
        }
        $sql = 'UPDATE ' . $this->q($this->table)
            . ' SET deleted_at = NOW()' . ($this->hasColumn('updated_at') ? ', updated_at = NOW()' : '')
            . ' WHERE id = ? AND deleted_at IS NULL';
        $stmt = $this->pdo()->prepare($sql);

        return $stmt->execute([$id]);
    }

    public function restore(int|string $id): bool
    {
        if (!$this->softDelete) {
            return false;
        }
        $stmt = $this->pdo()->prepare(
            'UPDATE ' . $this->q($this->table) . ' SET deleted_at = NULL WHERE id = ?'
        );

        return $stmt->execute([$id]);
    }

    /** Физическое удаление (осторожно: только для черновиков/регистров). */
    public function forceDelete(int|string $id): bool
    {
        $stmt = $this->pdo()->prepare('DELETE FROM ' . $this->q($this->table) . ' WHERE id = ?');

        return $stmt->execute([$id]);
    }

    // ---------------------------------------------------------------- произвольные запросы

    /** @return array<int, array<string, mixed>> */
    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** INSERT/UPDATE/DELETE — возвращает rowCount. */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /** Проверка существования колонки (кешируется). */
    private function hasColumn(string $column): bool
    {
        static $cache = [];
        if (!isset($cache[$this->table])) {
            $stmt = $this->pdo()->prepare('SHOW COLUMNS FROM ' . $this->q($this->table));
            $stmt->execute();
            $cache[$this->table] = array_column($stmt->fetchAll(), 'Field');
        }

        return in_array($column, $cache[$this->table], true);
    }
}
