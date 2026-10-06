<?php

namespace App\Modules\Foundation\Actions;

use App\Models\User;
use App\Modules\Foundation\Models\Note;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Soft-deletes a note: notes.delete_any, or notes.delete_own for its author.
 */
class DeleteNote
{
    /**
     * @throws AuthorizationException
     */
    public function handle(Note $note, User $actor): void
    {
        if (! self::allows($note, $actor)) {
            throw new AuthorizationException;
        }

        DB::transaction(fn () => $note->delete());
    }

    public static function allows(Note $note, User $actor): bool
    {
        if ($note->notable === null || ! $note->notable->isViewableBy($actor)) {
            return false;
        }

        return $actor->can('notes.delete_any')
            || ($actor->can('notes.delete_own') && $note->created_by === $actor->id);
    }
}
