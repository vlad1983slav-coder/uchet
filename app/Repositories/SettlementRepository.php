<?php

declare(strict_types=1);

namespace Uchet\Repositories;

use Uchet\Core\Repository;
use Uchet\Models\Settlement;

/** Регистр взаиморасчётов registers_settlements. */
final class SettlementRepository extends Repository
{
    public function __construct()
    {
        parent::__construct('registers_settlements', Settlement::class, false);
    }

    public function add(int $counterpartyId, string $documentType, int $documentId, string $docDate, float $amount): void
    {
        $this->execute(
            'INSERT INTO registers_settlements (counterparty_id, document_type, document_id, doc_date, amount, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())',
            [$counterpartyId, $documentType, $documentId, $docDate, $amount]
        );
    }

    public function deleteByDocument(string $documentType, int $documentId): void
    {
        $this->execute(
            'DELETE FROM registers_settlements WHERE document_type = ? AND document_id = ?',
            [$documentType, $documentId]
        );
    }

    /** Текущий долг контрагента: >0 — нам должны, <0 — мы должны. */
    public function balance(int $counterpartyId): float
    {
        $row = $this->queryOne(
            'SELECT COALESCE(SUM(amount), 0) AS balance FROM registers_settlements WHERE counterparty_id = ?',
            [$counterpartyId]
        );

        return (float) ($row['balance'] ?? 0);
    }

    /** @return array<int, array<string, mixed>> */
    public function byCounterparty(int $counterpartyId): array
    {
        return $this->query(
            'SELECT * FROM registers_settlements WHERE counterparty_id = ? ORDER BY doc_date, id',
            [$counterpartyId]
        );
    }
}
