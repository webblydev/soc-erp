<?php

namespace App\Modules\Projects\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One status or phase change of a project (docs/04 §3.7).
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $from_status_id
 * @property int|null $to_status_id
 * @property int|null $from_phase_id
 * @property int|null $to_phase_id
 * @property string|null $reason
 * @property int|null $changed_by
 * @property Carbon $changed_at
 * @property-read ProjectStatus|null $fromStatus
 * @property-read ProjectStatus|null $toStatus
 * @property-read ProjectPhase|null $fromPhase
 * @property-read ProjectPhase|null $toPhase
 * @property-read User|null $changer
 */
#[Fillable(['from_status_id', 'to_status_id', 'from_phase_id', 'to_phase_id', 'reason', 'changed_by', 'changed_at'])]
class ProjectStatusHistory extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<ProjectStatus, $this>
     */
    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(ProjectStatus::class, 'from_status_id');
    }

    /**
     * @return BelongsTo<ProjectStatus, $this>
     */
    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(ProjectStatus::class, 'to_status_id');
    }

    /**
     * @return BelongsTo<ProjectPhase, $this>
     */
    public function fromPhase(): BelongsTo
    {
        return $this->belongsTo(ProjectPhase::class, 'from_phase_id');
    }

    /**
     * @return BelongsTo<ProjectPhase, $this>
     */
    public function toPhase(): BelongsTo
    {
        return $this->belongsTo(ProjectPhase::class, 'to_phase_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
