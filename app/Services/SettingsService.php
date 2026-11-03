<?php

declare(strict_types=1);

namespace Uchet\Services;

use Uchet\Repositories\SettingsRepository;

/**
 * Настройки системы (таблица settings) со статическим кешем на запрос.
 * Ключи: stock_method (FIFO|AVG), closed_period_date, negative_stock (forbid|allow), префиксы документов.
 */
final class SettingsService
{
    private static ?array $cache = null;

    private function __construct() {}

    private static function repo(): SettingsRepository
    {
        static $repo = null;

        return $repo ??= new SettingsRepository();
    }

    private static function load(): array
    {
        return self::$cache ??= self::repo()->allAssoc();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::load();

        return $all[$key] ?? $default;
    }

    public static function set(string $key, string $value, ?string $description = null): void
    {
        self::repo()->set($key, $value, $description);
        self::$cache = null;
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }

    /** Метод списания партий: 'FIFO' | 'AVG'. */
    public static function stockMethod(): string
    {
        return strtoupper((string) self::get('stock_method', 'FIFO')) === 'AVG' ? 'AVG' : 'FIFO';
    }

    /** Дата закрытого периода (включительно): документы с датой <= не проводятся/не распроводятся. */
    public static function closedPeriodDate(): ?string
    {
        $value = self::get('closed_period_date');

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** Разрешены ли отрицательные остатки. */
    public static function negativeStockAllowed(): bool
    {
        return strtolower((string) self::get('negative_stock', 'forbid')) === 'allow';
    }

    /** Префикс документа по типу (из настроек, с дефолтами). */
    public static function prefixFor(string $type, ?string $direction = null): string
    {
        return match ($type) {
            'receipts' => (string) self::get('doc_prefix_receipts', 'ПН'),
            'issues' => (string) self::get('doc_prefix_issues', 'РН'),
            'moves' => (string) self::get('doc_prefix_moves', 'ПМ'),
            'inventories' => (string) self::get('doc_prefix_inv', 'ИНВ'),
            'returns' => $direction === 'from_customer'
                ? (string) self::get('doc_prefix_ret_cus', 'ВК')
                : (string) self::get('doc_prefix_ret_sup', 'ВП'),
            default => strtoupper(substr($type, 0, 3)),
        };
    }
}
