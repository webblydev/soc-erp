<?php

namespace App\Modules\Projects\Contracts;

use App\Modules\Projects\Models\Project;

/**
 * Something that must be settled before a project is COMPLETED (PRJ-BR-08, spec P2). Projects
 * checks tasks and approvals; Sales (06) and Accounting (08) add receivable and draft documents.
 */
interface ProjectCompletionCheck
{
    /**
     * Reasons the project cannot be completed yet; empty when it can.
     *
     * @return list<string>
     */
    public function check(Project $project): array;
}
