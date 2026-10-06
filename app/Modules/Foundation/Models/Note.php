<?php

namespace App\Modules\Foundation\Models;

use App\Models\User;
use App\Support\AuditTrail\Auditable;
use App\Support\Collaboration\Collaborative;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A quick note on any Collaborative record (docs/01 §3.10).
 *
 * @property int $id
 * @property string $notable_type
 * @property int $notable_id
 * @property string $body
 * @property bool $is_pinned
 * @property int $created_by
 * @property Carbon $created_at
 * @property-read (Model&Collaborative)|null $notable
 * @property-read User|null $author
 */
#[Fillable(['body', 'is_pinned', 'created_by'])]
class Note extends Model
{
    use Auditable, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_pinned' => 'boolean'];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function notable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Pinned notes first, then newest first.
     *
     * @param  Builder<static>  $query
     */
    public function scopeForDisplay(Builder $query): void
    {
        $query->orderByDesc('is_pinned')->latest('id');
    }
}
