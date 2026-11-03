<?php

declare(strict_types=1);

namespace Uchet\Models;

use Uchet\Core\Model;

/** Строка регистра взаиморасчётов: + долг покупателя нам, − наш долг поставщику. */
final class Settlement extends Model
{
    protected string $table = 'registers_settlements';

    protected array $fillable = ['counterparty_id', 'document_type', 'document_id', 'doc_date', 'amount'];
}
