<?php

declare(strict_types=1);

namespace Uchet\Repositories;

use Uchet\Core\Repository;

/** Настройки settings (ключ-значение, нет soft delete). */
final class SettingsRepository extends Repository
{
    public function __construct()
    {
        parent::__construct('settings', '', false);
    }

    /** @return array<string, string> — все настройки key => value */
    public function allAssoc(): array
    {
        $rows = $this->query('SELECT setting_key, value FROM settings');

        return array_column($rows, 'value', 'setting_key');
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $row = $this->queryOne('SELECT value FROM settings WHERE setting_key = ?', [$key]);

        return $row === null ? $default : (string) $row['value'];
    }

    public function set(string $key, string $value, ?string $description = null): void
    {
        $this->execute(
            'INSERT INTO settings (setting_key, value, description, updated_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE value = VALUES(value), description = COALESCE(VALUES(description), description), updated_at = NOW()',
            [$key, $value, $description]
        );
    }
}
