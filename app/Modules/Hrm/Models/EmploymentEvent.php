<?php

namespace App\Modules\Hrm\Models;

use App\Models\User;
use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\TracksAuthors;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One entry in an employee's employment history (docs/09 §3.4). Events are never edited or
 * deleted; RecordEmploymentEvent and the exit / rejoin Actions write them (spec H5).
 *
 * @property int $id
 * @property int $employee_id
 * @property int $employment_event_type_id
 * @property Carbon $effective_date
 * @property int|null $from_department_id
 * @property int|null $to_department_id
 * @property int|null $from_designation_id
 * @property int|null $to_designation_id
 * @property string|null $from_salary
 * @property string|null $to_salary
 * @property string|null $note
 * @property int|null $approved_by
 * @property-read EmploymentEventType $type
 */
#[Fillable([
    'employment_event_type_id', 'effective_date', 'from_department_id', 'to_department_id', 'from_designation_id', 'to_designation_id',
    'from_salary', 'to_salary', 'note', 'approved_by',
])]
class EmploymentEvent extends Model
{
    use Auditable, TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['effective_date' => 'date', 'from_salary' => 'decimal:2', 'to_salary' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<EmploymentEventType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(EmploymentEventType::class, 'employment_event_type_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    /**
     * @return BelongsTo<Designation, $this>
     */
    public function fromDesignation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'from_designation_id');
    }

    /**
     * @return BelongsTo<Designation, $this>
     */
    public function toDesignation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'to_designation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
