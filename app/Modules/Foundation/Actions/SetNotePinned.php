<?php

namespace App\Modules\Foundation\Actions;

use App\Models\User;
use App\Modules\Foundation\Models\Note;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Pins a note to the top of its record's notes, or unpins it.
 */
class SetNotePinned
{
    /**
     * @throws AuthorizationException
     */
    public function handle(Note $note, bool $pinned, User $actor): void
    {
        if (! self::allows($note, $actor)) {
            throw new AuthorizationException;
        }

        DB::transaction(fn () => $note->update(['is_pinned' => $pinned]));
    }

    public static function allows(Note $note, User $actor): bool
    {
        return $actor->can('notes.create') && $note->notable !== null && $note->notable->isViewableBy($actor);
    }
}
