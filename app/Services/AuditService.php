<?php

declare(strict_types=1);

namespace Uchet\Services;

use Uchet\Core\Auth;
use Uchet\Core\Request;
use Uchet\Repositories\AuditRepository;

/** Запись в журнал аудита (история изменений всех документов и справочников). */
final class AuditService
{
    private function __construct() {}

    /**
     * @param array<string, mixed>|null $oldData
     * @param array<string, mixed>|null $newData
     */
    public static function log(
        string $entityType,
        int|string $entityId,
        string $action,
        ?array $oldData = null,
        ?array $newData = null,
    ): void {
        try {
            (new AuditRepository())->log(
                Auth::id(),
                $entityType,
                $entityId,
                $action,
                $oldData,
                $newData,
                Request::ip(),
                Request::userAgent(),
            );
        } catch (\Throwable) {
            // аудит не должен ломать основную операцию
        }
    }
}
