<?php

namespace App\Modules\Hrm\Actions;

use App\Models\User;
use App\Modules\Hrm\Models\EmployeeDocument;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Removes an employee document and soft-deletes its attachment (spec H13). Document managers may
 * remove the file without the general attachments.delete_* permissions.
 */
class DeleteEmployeeDocument
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, EmployeeDocument $document): void
    {
        Gate::forUser($actor)->authorize('manageDocuments', $document->employee);

        DB::transaction(function () use ($document): void {
            $attachment = $document->attachment()->first();
            $document->delete();
            $attachment?->delete();
        });
    }
}
