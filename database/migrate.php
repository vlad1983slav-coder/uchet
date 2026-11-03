<?php

declare(strict_types=1);

/**
 * Миграционный раннер (CLI): php database/migrate.php
 *
 * Применяет в порядке очереди:
 *   1. database/schema.sql  — полная схема БД (создаёт БД uchet);
 *   2. database/seeds.sql   — тестовые данные;
 *   3. database/migrations/*.sql — инкрементальные миграции (по алфавиту).
 * Применённые файлы отмечаются в таблице schema_migrations.
 *
 * Подключение берётся из config/config.php (или переменных окружения DB_*).
 */

define('ROOT', dirname(__DIR__));

require ROOT . '/app/Core/helpers.php';
spl_autoload_register(static function (string $class): void {
    $prefix = 'Uchet\\';
    if (str_starts_with($class, $prefix)) {
        $file = ROOT . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

use Uchet\Core\Database;

function out(string $msg): void
{
    echo $msg . PHP_EOL;
}

/** Разбить SQL-файл на операторы (учитываем комментарии -- и переводы строк). */
function splitSql(string $sql): array
{
    $statements = [];
    $current = '';
    foreach (preg_split('/\r?\n/', $sql) as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '--')) {
            continue;
        }
        $current .= $line . "\n";
        if (str_ends_with($trimmed, ';')) {
            $statements[] = trim(substr($current, 0, -1));
            $current = '';
        }
    }
    if (trim($current) !== '') {
        $statements[] = trim($current);
    }

    return array_values(array_filter($statements, static fn (string $s) => trim($s) !== ''));
}

// --- подключение к серверу (без указания БД) для создания схемы ---
$cfg = (array) config('db', []);
$serverDsn = sprintf(
    'mysql:host=%s;port=%d;charset=utf8mb4',
    (string) ($cfg['host'] ?? '127.0.0.1'),
    (int) ($cfg['port'] ?? 3306)
);
$server = new PDO($serverDsn, (string) ($cfg['user'] ?? 'root'), (string) ($cfg['pass'] ?? ''), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$server->exec('CREATE DATABASE IF NOT EXISTS `' . ($cfg['name'] ?? 'uchet') . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
out('База `' . ($cfg['name'] ?? 'uchet') . '` создана (или уже существовала).');

$pdo = Database::connection();
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$queue = [
    'database/schema.sql',
    'database/seeds.sql',
];
foreach (glob(ROOT . '/database/migrations/*.sql') ?: [] as $migrationFile) {
    $queue[] = 'database/migrations/' . basename($migrationFile);
}

$applied = $pdo->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$applied = array_flip($applied);

foreach ($queue as $relative) {
    if (isset($applied[$relative])) {
        out("[skip] {$relative} — уже применён");
        continue;
    }
    $path = ROOT . '/' . $relative;
    if (!is_file($path)) {
        out("[!] пропущен, файл не найден: {$relative}");
        continue;
    }
    out("[apply] {$relative} ...");
    $sql = (string) file_get_contents($path);
    // schema.sql содержит CREATE DATABASE/USE — для него выполняем весь файл целиком
    if (str_contains($sql, 'CREATE DATABASE')) {
        foreach (splitSql($sql) as $statement) {
            $pdo->exec($statement);
        }
    } else {
        $pdo->exec('USE `' . ($cfg['name'] ?? 'uchet') . '`');
        foreach (splitSql($sql) as $statement) {
            $pdo->exec($statement);
        }
    }
    $stmt = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (?)');
    $stmt->execute([$relative]);
    out("[ok] {$relative}");
}

out('Готово. Миграций применено: ' . count($applied) . ' ранее + новые. Войти: admin / admin123');
