<?php

declare(strict_types=1);

namespace Uchet\Repositories;

use Uchet\Core\Repository;

/** Журнал аудита audit_log (нет soft delete). */
final class AuditRepository extends Repository
{
    public function __construct()
    {
        parent::__construct('audit_log', '', false);
    }

    /**
     * @param array<string, mixed>|null $oldData
     * @param array<string, mixed>|null $newData
     */
    public function log(
        ?int $userId,
        string $entityType,
        int|string $entityId,
        string $action,
        ?array $oldData,
        ?array $newData,
        string $ip = '',
        string $userAgent = '',
    ): void {
        $this->execute(
            'INSERT INTO audit_log (user_id, entity_type, entity_id, action, old_data, new_data, ip, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $userId,
                $entityType,
                (string) $entityId,
                $action,
                $oldData === null ? null : json_encode($oldData, JSON_UNESCAPED_UNICODE),
                $newData === null ? null : json_encode($newData, JSON_UNESCAPED_UNICODE),
                $ip,
                $userAgent,
            ]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function forEntity(string $entityType, int|string $entityId): array
    {
        return $this->query(
            'SELECT a.*, u.login, u.full_name
             FROM audit_log a LEFT JOIN users u ON u.id = a.user_id
             WHERE a.entity_type = ? AND a.entity_id = ?
             ORDER BY a.id DESC LIMIT 200',
            [$entityType, (string) $entityId]
        );
    }
}
