<?php

namespace App\Modules\Projects\Models;

use App\Modules\Hrm\Models\Employee;
use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\TracksAuthors;
use Database\Factories\Projects\ProjectEmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A project team membership (docs/04 §3.6). One active row per employee and role (spec P13).
 *
 * @property int $id
 * @property int $project_id
 * @property int $employee_id
 * @property int $project_role_id
 * @property string|null $allocation_pct
 * @property Carbon $assigned_on
 * @property Carbon|null $released_on
 * @property bool $is_active
 * @property string|null $notes
 * @property-read Employee $employee
 * @property-read ProjectRole $role
 * @property-read Project $project
 */
#[Fillable(['employee_id', 'project_role_id', 'allocation_pct', 'assigned_on', 'released_on', 'is_active', 'notes'])]
#[UseFactory(ProjectEmployeeFactory::class)]
class ProjectEmployee extends Model
{
    /** @use HasFactory<ProjectEmployeeFactory> */
    use Auditable, HasFactory, TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allocation_pct' => 'decimal:2',
            'assigned_on' => 'date',
            'released_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<ProjectRole, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(ProjectRole::class, 'project_role_id');
    }
}
