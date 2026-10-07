<?php

namespace App\Modules\Projects\Models;

use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\TracksAuthors;
use Database\Factories\Projects\PaymentScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A payment milestone of a project (docs/04 §3.5). Status moves through ScheduleTriggers and,
 * from 06, invoicing (spec P12).
 *
 * @property int $id
 * @property int $project_id
 * @property int $sort_order
 * @property string $milestone_name
 * @property int $schedule_trigger_id
 * @property int|null $trigger_ref_id
 * @property Carbon|null $due_date
 * @property string|null $percent
 * @property string $amount
 * @property int $schedule_status_id
 * @property Carbon|null $due_at
 * @property int|null $invoice_id
 * @property-read ScheduleTrigger $trigger
 * @property-read ScheduleStatus $status
 * @property-read Project $project
 */
#[Fillable(['sort_order', 'milestone_name', 'schedule_trigger_id', 'trigger_ref_id', 'due_date', 'percent', 'amount'])]
#[UseFactory(PaymentScheduleFactory::class)]
class PaymentSchedule extends Model
{
    /** @use HasFactory<PaymentScheduleFactory> */
    use Auditable, HasFactory, TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'due_date' => 'date',
            'percent' => 'decimal:4',
            'amount' => 'decimal:2',
            'due_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status->code === ScheduleStatus::PENDING;
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<ScheduleTrigger, $this>
     */
    public function trigger(): BelongsTo
    {
        return $this->belongsTo(ScheduleTrigger::class, 'schedule_trigger_id');
    }

    /**
     * @return BelongsTo<ScheduleStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(ScheduleStatus::class, 'schedule_status_id');
    }
}
