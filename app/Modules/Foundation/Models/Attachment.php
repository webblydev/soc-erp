<?php

namespace App\Modules\Foundation\Models;

use App\Models\User;
use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\TracksAuthors;
use App\Support\Collaboration\Collaborative;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

/**
 * A file stored outside the web root against any Collaborative record (docs/01 §3.9).
 *
 * @property int $id
 * @property string $attachable_type
 * @property int $attachable_id
 * @property int|null $document_type_id
 * @property string|null $title
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property int $version
 * @property int|null $replaces_attachment_id
 * @property int $uploaded_by
 * @property Carbon $created_at
 * @property-read (Model&Collaborative)|null $attachable
 * @property-read DocumentType|null $documentType
 * @property-read User|null $uploader
 * @property-read Attachment|null $replaces
 */
#[Fillable(['document_type_id', 'title', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'version', 'replaces_attachment_id', 'uploaded_by'])]
class Attachment extends Model
{
    use Auditable, SoftDeletes, TracksAuthors;

    /** How long a signed download link stays valid. */
    public const DOWNLOAD_MINUTES = 30;

    /** How long uploaders may delete their own files with attachments.delete_own. */
    public const DELETE_OWN_HOURS = 24;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'version' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<DocumentType, $this>
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<Attachment, $this>
     */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_attachment_id');
    }

    /**
     * @return HasOne<Attachment, $this>
     */
    public function replacement(): HasOne
    {
        return $this->hasOne(self::class, 'replaces_attachment_id');
    }

    /**
     * Only the newest version of each file: rows no live row replaces.
     *
     * @param  Builder<static>  $query
     */
    public function scopeLatestVersions(Builder $query): void
    {
        $query->whereDoesntHave('replacement');
    }

    /**
     * Earlier live versions of this file, newest first.
     *
     * @return Collection<int, Attachment>
     */
    public function previousVersions(): Collection
    {
        $versions = collect();
        $current = $this->replaces;

        while ($current !== null) {
            $versions->push($current);
            $current = $current->replaces;
        }

        return $versions;
    }

    public function isLatestVersion(): bool
    {
        return ! $this->replacement()->exists();
    }

    public function downloadUrl(): string
    {
        return URL::temporarySignedRoute('attachments.download', now()->addMinutes(self::DOWNLOAD_MINUTES), ['attachment' => $this->id]);
    }
}
