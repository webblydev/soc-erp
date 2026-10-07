<?php

namespace App\Modules\Projects\Models;

use App\Modules\Hrm\Models\Employee;
use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\TracksAuthors;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Hours an employee spent on a task (docs/04 §3.9).
 *
 * @property int $id
 * @property int $task_id
 * @property int $employee_id
 * @property Carbon $work_date
 * @property string $hours
 * @property string|null $note
 * @property-read Employee $employee
 */
#[Fillable(['employee_id', 'work_date', 'hours', 'note'])]
class TaskTimeLog extends Model
{
    use Auditable, TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['work_date' => 'date', 'hours' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
