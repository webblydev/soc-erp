<?php

namespace App\Support\Collaboration;

use App\Modules\Foundation\Models\Note;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Polymorphic quick notes (docs/01 §3.10). Models using it implement Collaborative.
 */
trait HasNotes
{
    /**
     * @return MorphMany<Note, $this>
     */
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable');
    }
}
