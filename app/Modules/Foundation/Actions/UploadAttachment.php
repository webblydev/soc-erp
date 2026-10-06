<?php

namespace App\Modules\Foundation\Actions;

use App\Models\User;
use App\Modules\Foundation\Models\Attachment;
use App\Modules\Foundation\Models\DocumentType;
use App\Support\Collaboration\Collaborative;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Stores a file against a record (docs/01 §3.9, FD-BR-08). Passing $replaces uploads the next
 * version of that file, keeping its document type and title.
 */
class UploadAttachment
{
    /**
     * @param  array{document_type_id?: int|string|null, title?: string|null}  $input
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(Model&Collaborative $parent, UploadedFile $file, User $actor, array $input = [], ?Attachment $replaces = null): Attachment
    {
        if (! $actor->can('attachments.upload') || ! $parent->isViewableBy($actor)) {
            throw new AuthorizationException;
        }

        if ($replaces !== null) {
            $this->ensureReplaceable($parent, $replaces);
            $input = ['document_type_id' => $replaces->document_type_id, 'title' => $replaces->title];
        }

        $input = array_map(fn (mixed $value): mixed => $value === '' ? null : $value, $input);

        /** @var array{document_type_id?: int|null, title?: string|null} $data */
        $data = Validator::make($input, [
            'document_type_id' => ['nullable', 'integer', $replaces === null ? Rule::exists('document_types', 'id')->where('is_active', true)->whereNull('deleted_at') : Rule::exists('document_types', 'id')],
            'title' => ['nullable', 'string', 'max:200'],
        ])->validate();

        $type = isset($data['document_type_id']) ? DocumentType::query()->find($data['document_type_id']) : null;
        $this->validateFile($file, $type);

        $disk = (string) config('foundation.attachments.disk');
        $extension = Str::lower($file->getClientOriginalExtension());
        $path = $file->storeAs("attachments/{$parent->getMorphClass()}/{$parent->getKey()}", Str::uuid().'.'.$extension, $disk);

        return DB::transaction(function () use ($parent, $file, $actor, $data, $replaces, $disk, $path): Attachment {
            if ($replaces !== null) {
                Attachment::query()->whereKey($replaces->id)->lockForUpdate()->first();
                $this->ensureReplaceable($parent, $replaces);
            }

            /** @var Attachment $attachment */
            $attachment = $parent->morphMany(Attachment::class, 'attachable')->create([
                'document_type_id' => $data['document_type_id'] ?? null,
                'title' => $data['title'] ?? null,
                'disk' => $disk,
                'path' => (string) $path,
                'original_name' => Str::limit($file->getClientOriginalName(), 252),
                'mime_type' => Str::limit((string) ($file->getMimeType() ?? $file->getClientMimeType()), 97),
                'size_bytes' => (int) $file->getSize(),
                'version' => $replaces !== null ? $replaces->version + 1 : 1,
                'replaces_attachment_id' => $replaces?->id,
                'uploaded_by' => $actor->id,
            ]);

            return $attachment;
        });
    }

    /**
     * @throws ValidationException
     */
    private function validateFile(UploadedFile $file, ?DocumentType $type): void
    {
        $extensions = $type?->allowedExtensions() ?? (array) config('foundation.attachments.extensions');
        $maxMegabytes = $type->max_size_mb ?? (int) config('foundation.attachments.max_size_mb');

        Validator::make(['file' => $file], [
            'file' => ['required', 'file', 'extensions:'.implode(',', $extensions), 'max:'.($maxMegabytes * 1024)],
        ])->validate();
    }

    /**
     * @throws ValidationException
     */
    private function ensureReplaceable(Model $parent, Attachment $replaces): void
    {
        if ($replaces->attachable_type !== $parent->getMorphClass() || $replaces->attachable_id !== $parent->getKey()) {
            throw ValidationException::withMessages(['file' => __('That file belongs to another record.')]);
        }

        if (! $replaces->isLatestVersion()) {
            throw ValidationException::withMessages(['file' => __('Only the latest version of a file can be replaced.')]);
        }
    }
}
