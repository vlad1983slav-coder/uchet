<?php

declare(strict_types=1);

namespace Uchet\Repositories;

use Uchet\Models\Receipt;
use Uchet\Models\ReceiptLine;

/** Приходные накладные (ПН). */
final class ReceiptRepository extends DocumentRepository
{
    public function __construct()
    {
        parent::__construct('documents_receipts', 'documents_receipts_lines', Receipt::class, ReceiptLine::class, 'ПН');
    }
}
