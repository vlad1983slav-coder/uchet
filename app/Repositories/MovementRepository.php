<?php

declare(strict_types=1);

namespace Uchet\Repositories;

use Uchet\Core\Repository;
use Uchet\Models\Movement;

/** Регистр движений registers_movements. */
final class MovementRepository extends Repository
{
    public function __construct()
    {
        parent::__construct('registers_movements', Movement::class, false);
    }

    /** Записать движение по документу. */
    public function add(
        string $documentType,
        int $documentId,
        string $docDate,
        int $warehouseId,
        int $nomenclatureId,
        ?int $partyId,
        float $qtyIn,
        float $qtyOut,
        float $amountIn,
        float $amountOut,
    ): void {
        $this->execute(
            'INSERT INTO registers_movements
                (document_type, document_id, doc_date, warehouse_id, nomenclature_id, party_id,
                 quantity_in, quantity_out, amount_in, amount_out, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [$documentType, $documentId, $docDate, $warehouseId, $nomenclatureId, $partyId,
                $qtyIn, $qtyOut, $amountIn, $amountOut]
        );
    }

    /** @return array<int, array<string, mixed>> — все движения документа */
    public function byDocument(string $documentType, int $documentId): array
    {
        return $this->query(
            'SELECT * FROM registers_movements WHERE document_type = ? AND document_id = ? ORDER BY id',
            [$documentType, $documentId]
        );
    }

    public function deleteByDocument(string $documentType, int $documentId): void
    {
        $this->execute(
            'DELETE FROM registers_movements WHERE document_type = ? AND document_id = ?',
            [$documentType, $documentId]
        );
    }
}
