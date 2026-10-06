<?php

namespace App\Modules\Foundation\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Soft-deletes an unused, non-system lookup row; rows referenced elsewhere must be deactivated
 * (FD-BR-06). A soft delete never trips a foreign key, so every column with a foreign key to the
 * table is checked, counting deleted records that still point at the row.
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

        if ($this->isReferenced($table, (int) $row->getKey())) {
            throw ValidationException::withMessages(['row' => __('In use — deactivate instead.')]);
        }

        DB::transaction(fn () => $row->delete());
    }

    private function isReferenced(string $table, int $id): bool
    {
        foreach (Schema::getTableListing(Schema::getCurrentSchemaName(), schemaQualified: false) as $referencing) {
            foreach (Schema::getForeignKeys($referencing) as $foreignKey) {
                if ($foreignKey['foreign_table'] !== $table || count($foreignKey['columns']) !== 1) {
                    continue;
                }

                if (DB::table($referencing)->where($foreignKey['columns'][0], $id)->exists()) {
                    return true;
                }
            }
        }

        return false;
    }
}
