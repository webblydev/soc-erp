<?php

namespace App\Modules\Foundation\Livewire\Shared;

use App\Modules\Foundation\Actions\AddNote;
use App\Modules\Foundation\Actions\DeleteNote;
use App\Modules\Foundation\Actions\SetNotePinned;
use App\Modules\Foundation\Concerns\InteractsWithCollaborativeParent;
use App\Modules\Foundation\Models\Note;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

/**
 * The shared notes panel (docs/01 §5.14): add, pin and delete quick notes.
 */
class Notes extends Component
{
    use InteractsWithCollaborativeParent;

    public string $body = '';

    public ?int $deletingId = null;

    public function mount(Model $model): void
    {
        $this->rememberParent($model);
    }

    public function add(AddNote $addNote): void
    {
        $this->authorize('notes.create');

        $addNote->handle($this->parent(), ['body' => $this->body], $this->actor());

        $this->reset('body');
        $this->dispatch('toast', type: 'success', description: __('Note added.'));
    }

    public function togglePin(int $id, SetNotePinned $setNotePinned): void
    {
        $note = $this->findNote($id);

        try {
            $setNotePinned->handle($note, ! $note->is_pinned, $this->actor());
        } catch (AuthorizationException) {
            $this->dispatch('toast', type: 'error', description: __('You cannot change this note.'));
        }
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $this->findNote($id)->id;
        $this->dispatch('open-sheet-note-delete');
    }

    public function delete(int $id, DeleteNote $deleteNote): void
    {
        $this->deletingId = null;
        $this->dispatch('close-sheet-note-delete');

        try {
            $deleteNote->handle($this->findNote($id), $this->actor());
        } catch (AuthorizationException) {
            $this->dispatch('toast', type: 'error', description: __('You cannot delete this note.'));

            return;
        }

        $this->dispatch('toast', type: 'success', description: __('Note deleted.'));
    }

    public function render(): View
    {
        $actor = $this->actor();

        return view('livewire.shared.notes', [
            'notes' => $this->parent()->morphMany(Note::class, 'notable')->forDisplay()->with('author:id,name')->get(),
            'canDelete' => fn (Note $note): bool => $actor->can('notes.delete_any') || ($actor->can('notes.delete_own') && $note->created_by === $actor->id),
        ]);
    }

    private function findNote(int $id): Note
    {
        /** @var Note $note */
        $note = $this->parent()->morphMany(Note::class, 'notable')->findOrFail($id);

        return $note;
    }
}
