<?php

namespace App\Modules\Foundation\Actions;

use App\Models\User;
use App\Modules\Foundation\Models\Note;
use App\Support\Collaboration\Collaborative;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Adds a quick note to a record (docs/01 §3.10).
 */
class AddNote
{
    /**
     * @param  array{body?: string|null}  $input
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(Model&Collaborative $parent, array $input, User $actor): Note
    {
        if (! $actor->can('notes.create') || ! $parent->isViewableBy($actor)) {
            throw new AuthorizationException;
        }

        /** @var array{body: string} $data */
        $data = Validator::make(['body' => trim((string) ($input['body'] ?? ''))], [
            'body' => ['required', 'string', 'max:5000'],
        ])->validate();

        return DB::transaction(function () use ($parent, $data, $actor): Note {
            /** @var Note $note */
            $note = $parent->morphMany(Note::class, 'notable')->create([
                'body' => $data['body'],
                'is_pinned' => false,
                'created_by' => $actor->id,
            ]);

            return $note;
        });
    }
}
