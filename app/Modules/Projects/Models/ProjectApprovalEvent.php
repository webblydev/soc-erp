<?php

namespace App\Modules\Projects\Models;

use App\Models\User;
use App\Modules\Foundation\Models\Attachment;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One step in an approval's trail (docs/04 §3.10): submitted, query raised, approved…
 *
 * @property int $id
 * @property int $project_approval_id
 * @property int $approval_status_id
 * @property Carbon $event_date
 * @property string|null $note
 * @property int|null $attachment_id
 * @property int|null $created_by
 * @property-read ApprovalStatus $status
 * @property-read Attachment|null $attachment
 * @property-read User|null $creator
 */
#[Fillable(['approval_status_id', 'event_date', 'note', 'attachment_id', 'created_by'])]
class ProjectApprovalEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['event_date' => 'date'];
    }

    /**
     * @return BelongsTo<ApprovalStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(ApprovalStatus::class, 'approval_status_id');
    }

    /**
     * @return BelongsTo<Attachment, $this>
     */
    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
