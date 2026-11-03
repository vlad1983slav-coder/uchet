<?php

declare(strict_types=1);

namespace Uchet\Services;

use Uchet\Repositories\SettlementRepository;

/**
 * Взаиморасчёты с контрагентами.
 * Знак amount: + долг покупателя нам (РН, возврат поставщику уменьшает наш долг),
 *              − наш долг поставщику (ПН, возврат от покупателя уменьшает долг покупателя).
 */
final class SettlementService
{
    public function __construct(
        private readonly SettlementRepository $settlements = new SettlementRepository(),
    ) {}

    public function add(int $counterpartyId, string $documentType, int $documentId, string $docDate, float $amount): void
    {
        $this->settlements->add($counterpartyId, $documentType, $documentId, $docDate, $amount);
    }

    public function deleteByDocument(string $documentType, int $documentId): void
    {
        $this->settlements->deleteByDocument($documentType, $documentId);
    }

    public function balance(int $counterpartyId): float
    {
        return $this->settlements->balance($counterpartyId);
    }

    /** @return array<int, array<string, mixed>> */
    public function history(int $counterpartyId): array
    {
        return $this->settlements->byCounterparty($counterpartyId);
    }
}
