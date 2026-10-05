<?php

namespace App\Modules\Foundation\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deletes an unused, non-system lookup row; rows referenced elsewhere must be deactivated (FD-BR-06).
 */
class DeleteLookup
{
    /**
     * @throws ValidationException
     */
    public function handle(string $table, Model $row): void
    {
        if ($row->getAttribute('is_system')) {
            throw ValidationException::withMessages(['row' => __('System rows cannot be deleted.')]);
        }

        try {
            DB::transaction(fn () => $row->delete());
        } catch (QueryException $exception) {
            if (str_starts_with((string) ($exception->errorInfo[0] ?? $exception->getCode()), '23')) {
                throw ValidationException::withMessages(['row' => __('In use — deactivate instead.')]);
            }

            throw $exception;
        }
    }
}
