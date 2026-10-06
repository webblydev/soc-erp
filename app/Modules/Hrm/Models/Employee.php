<?php

namespace App\Modules\Hrm\Models;

use App\Models\User;
use App\Modules\Foundation\Models\Branch;
use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\TracksAuthors;
use App\Support\Collaboration\Collaborative;
use App\Support\Collaboration\HasAttachments;
use App\Support\Collaboration\HasNotes;
use Database\Factories\Hrm\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A member of staff (docs/09 §3.2). Code, full name, department, designation, salary and bank
 * fields, photo and exit fields are not fillable: their Actions set them (spec H3, H5, H7, H16).
 *
 * @property int $id
 * @property string $employee_code
 * @property string $first_name
 * @property string|null $last_name
 * @property string $full_name
 * @property string|null $father_name
 * @property string|null $mother_name
 * @property int|null $gender_id
 * @property Carbon|null $date_of_birth
 * @property int|null $marital_status_id
 * @property int|null $blood_group_id
 * @property string|null $nid_number
 * @property string $phone
 * @property string|null $personal_email
 * @property string|null $official_email
 * @property string|null $present_address
 * @property string|null $permanent_address
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_relation
 * @property string|null $emergency_contact_phone
 * @property string|null $reference
 * @property string|null $photo_path
 * @property int $department_id
 * @property int $designation_id
 * @property int $employee_type_id
 * @property int $employee_status_id
 * @property int|null $branch_id
 * @property int|null $manager_id
 * @property Carbon $joining_date
 * @property Carbon|null $confirmation_date
 * @property Carbon|null $exit_date
 * @property int|null $exit_reason_id
 * @property string|null $bank_name
 * @property string|null $bank_account_no
 * @property string|null $mobile_wallet_no
 * @property string|null $gross_salary
 * @property string|null $tin
 * @property string|null $notes
 * @property Carbon|null $probation_notified_at
 * @property Carbon $created_at
 * @property-read Department $department
 * @property-read Designation $designation
 * @property-read EmployeeType $type
 * @property-read EmployeeStatus $status
 * @property-read Employee|null $manager
 * @property-read User|null $user
 */
#[Fillable([
    'first_name', 'last_name', 'father_name', 'mother_name', 'gender_id', 'date_of_birth', 'marital_status_id', 'blood_group_id',
    'nid_number', 'phone', 'personal_email', 'official_email', 'present_address', 'permanent_address',
    'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_phone', 'reference',
    'employee_type_id', 'employee_status_id', 'branch_id', 'manager_id', 'joining_date', 'confirmation_date', 'tin', 'notes',
])]
#[UseFactory(EmployeeFactory::class)]
class Employee extends Model implements Collaborative
{
    /** @use HasFactory<EmployeeFactory> */
    use Auditable, HasAttachments, HasFactory, HasNotes, SoftDeletes, TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'joining_date' => 'date',
            'confirmation_date' => 'date',
            'exit_date' => 'date',
            'gross_salary' => 'decimal:2',
            'probation_notified_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'employee_code';
    }

    /**
     * Attachments, notes and history hold personal data (NID scans, salary changes), so the
     * shared collaboration panels need full visibility, not just the directory view (spec H2).
     */
    public function isViewableBy(User $user): bool
    {
        return $user->can('viewFull', $this);
    }

    /**
     * Employees in active employment, the only ones that can be assigned (HR-BR-06).
     *
     * @param  Builder<static>  $query
     */
    public function scopeAssignable(Builder $query): void
    {
        $query->whereIn($this->qualifyColumn('employee_status_id'), EmployeeStatus::query()->select('id')->where('is_active_employment', true));
    }

    public function isInActiveEmployment(): bool
    {
        return (bool) $this->status->is_active_employment;
    }

    public function hasExited(): bool
    {
        return (bool) $this->status->is_exit;
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path !== null ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function initials(): string
    {
        $initials = Str::initials($this->full_name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Designation, $this>
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    /**
     * @return BelongsTo<EmployeeType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(EmployeeType::class, 'employee_type_id');
    }

    /**
     * @return BelongsTo<EmployeeStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(EmployeeStatus::class, 'employee_status_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    /**
     * Direct reports.
     *
     * @return HasMany<Employee, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id')->orderBy('full_name');
    }

    /**
     * The login linked through users.employee_id (FD-BR-03).
     *
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * @return HasMany<EmployeeDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    /**
     * Employment history, newest first.
     *
     * @return HasMany<EmploymentEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(EmploymentEvent::class)->orderByDesc('effective_date')->orderByDesc('id');
    }

    /**
     * @return HasMany<EmployeeEducation, $this>
     */
    public function education(): HasMany
    {
        return $this->hasMany(EmployeeEducation::class)->orderBy('id');
    }

    /**
     * @return HasMany<EmployeeExperience, $this>
     */
    public function experience(): HasMany
    {
        return $this->hasMany(EmployeeExperience::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<Gender, $this>
     */
    public function gender(): BelongsTo
    {
        return $this->belongsTo(Gender::class);
    }

    /**
     * @return BelongsTo<MaritalStatus, $this>
     */
    public function maritalStatus(): BelongsTo
    {
        return $this->belongsTo(MaritalStatus::class);
    }

    /**
     * @return BelongsTo<BloodGroup, $this>
     */
    public function bloodGroup(): BelongsTo
    {
        return $this->belongsTo(BloodGroup::class);
    }

    /**
     * @return BelongsTo<ExitReason, $this>
     */
    public function exitReason(): BelongsTo
    {
        return $this->belongsTo(ExitReason::class);
    }
}
