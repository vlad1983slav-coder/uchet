<?php

declare(strict_types=1);

namespace Uchet\Repositories;

use Uchet\Models\Issue;
use Uchet\Models\IssueLine;

/** Расходные накладные (РН). */
final class IssueRepository extends DocumentRepository
{
    public function __construct()
    {
        parent::__construct('documents_issues', 'documents_issues_lines', Issue::class, IssueLine::class, 'РН');
    }
}
