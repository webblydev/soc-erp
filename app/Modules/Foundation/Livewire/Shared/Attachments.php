<?php

namespace App\Modules\Foundation\Livewire\Shared;

use App\Modules\Foundation\Actions\DeleteAttachment;
use App\Modules\Foundation\Actions\UploadAttachment;
use App\Modules\Foundation\Concerns\InteractsWithCollaborativeParent;
use App\Modules\Foundation\Models\Attachment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * The shared attachments panel (docs/01 §5.14): upload, replace, versions, signed downloads, delete.
 */
class Attachments extends Component
{
    use InteractsWithCollaborativeParent, WithFileUploads;

    public ?TemporaryUploadedFile $upload = null;

    public ?int $documentTypeId = null;

    public string $title = '';

    public ?int $replacingId = null;

    public ?int $selectedId = null;

    public function mount(Model $model): void
    {
        $this->rememberParent($model);
    }

    public function startUpload(): void
    {
        $this->resetForm();
        $this->dispatch('open-sheet-attachment-upload');
    }

    public function startReplace(int $id): void
    {
        $attachment = $this->findAttachment($id);

        $this->resetForm();
        $this->replacingId = $attachment->id;
        $this->dispatch('close-sheet-attachment-actions');
        $this->dispatch('open-sheet-attachment-upload');
    }

    public function showActions(int $id): void
    {
        $this->selectedId = $this->findAttachment($id)->id;
        $this->dispatch('open-sheet-attachment-actions');
    }

    public function save(UploadAttachment $uploadAttachment): void
    {
        $this->authorize('attachments.upload');

        if ($this->upload === null) {
            throw ValidationException::withMessages(['upload' => __('Choose a file to upload.')]);
        }

        $replaces = $this->replacingId !== null ? $this->findAttachment($this->replacingId) : null;

        try {
            $uploadAttachment->handle($this->parent(), $this->upload, $this->actor(), [
                'document_type_id' => $this->documentTypeId,
                'title' => $this->title,
            ], $replaces);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => [$key === 'file' ? 'upload' : $key => $messages])->all(),
            );
        }

        $this->resetForm();
        $this->dispatch('close-sheet-attachment-upload');
        $this->dispatch('toast', type: 'success', description: $replaces !== null ? __('New version uploaded.') : __('File uploaded.'));
    }

    public function delete(int $id, DeleteAttachment $deleteAttachment): void
    {
        try {
            $deleteAttachment->handle($this->findAttachment($id), $this->actor());
        } catch (AuthorizationException) {
            $this->dispatch('toast', type: 'error', description: __('You cannot delete this file.'));

            return;
        }

        $this->selectedId = null;
        $this->dispatch('close-sheet-attachment-actions');
        $this->dispatch('toast', type: 'success', description: __('File deleted.'));
    }

    public function render(): View
    {
        $parent = $this->parent();
        $selected = $this->selectedId !== null ? $parent->morphMany(Attachment::class, 'attachable')->find($this->selectedId) : null;

        return view('livewire.shared.attachments', [
            'attachments' => $parent->morphMany(Attachment::class, 'attachable')->latestVersions()
                ->with(['documentType:id,name', 'uploader:id,name'])->latest('id')->get(),
            'replacing' => $this->replacingId !== null ? $parent->morphMany(Attachment::class, 'attachable')->find($this->replacingId) : null,
            'selected' => $selected,
            'canDeleteSelected' => $selected !== null && DeleteAttachment::allows($selected, $this->actor()),
            'acceptedExtensions' => collect((array) config('foundation.attachments.extensions'))->map(fn (string $extension): string => '.'.$extension)->implode(','),
        ]);
    }

    private function findAttachment(int $id): Attachment
    {
        /** @var Attachment $attachment */
        $attachment = $this->parent()->morphMany(Attachment::class, 'attachable')->findOrFail($id);

        return $attachment;
    }

    private function resetForm(): void
    {
        $this->reset(['upload', 'documentTypeId', 'title', 'replacingId']);
        $this->resetErrorBag();
    }
}
