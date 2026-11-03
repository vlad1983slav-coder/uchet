<?php

declare(strict_types=1);

namespace Uchet\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Singleton-обёртка над PDO (MySQL).
 * - только prepared statements (emulate prepares выключен);
 * - ERRMODE_EXCEPTION — любая ошибка SQL = исключение;
 * - транзакции: beginTransaction()/commit()/rollBack() — используются сервисом проведения документов.
 */
final class Database
{
    private static ?PDO $pdo = null;

    private function __construct() {}

    private function __clone() {}

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $cfg = (array) config('db', []);
        $host = (string) ($cfg['host'] ?? '127.0.0.1');
        $port = (int) ($cfg['port'] ?? 3306);
        $name = (string) ($cfg['name'] ?? 'uchet');
        $user = (string) ($cfg['user'] ?? 'root');
        $pass = (string) ($cfg['pass'] ?? '');
        $charset = (string) ($cfg['charset'] ?? 'utf8mb4');

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Не удалось подключиться к БД: ' . $e->getMessage(), 0, $e);
        }

        return self::$pdo;
    }

    /** Алиас connection(). */
    public static function pdo(): PDO
    {
        return self::connection();
    }

    public static function beginTransaction(): bool
    {
        return self::connection()->beginTransaction();
    }

    public static function commit(): bool
    {
        return self::connection()->commit();
    }

    public static function rollBack(): bool
    {
        if (self::connection()->inTransaction()) {
            return self::connection()->rollBack();
        }

        return true;
    }

    public static function inTransaction(): bool
    {
        return self::connection()->inTransaction();
    }

    public static function lastInsertId(): string
    {
        return self::connection()->lastInsertId();
    }

    /**
     * Безопасное квотирование идентификатора (таблица/колонка) для ORDER BY, JOIN и т.п.
     * Разрешены только [A-Za-z0-9_], иначе — исключение (защита от инъекций в ORDER BY).
     */
    public static function quoteIdentifier(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException("Недопустимый идентификатор: {$identifier}");
        }

        return '`' . $identifier . '`';
    }
}
