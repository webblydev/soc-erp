<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Hrm\Models\Employee;
use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\IsLookup;
use Database\Factories\Catalog\BusinessLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A division that owns work and numbers its projects (docs/02 §3.1).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $project_prefix
 * @property int|null $revenue_account_id
 * @property int|null $manager_employee_id
 * @property bool $is_internal
 * @property bool $is_active
 * @property bool $is_system
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system', 'project_prefix', 'is_internal', 'manager_employee_id'])]
#[UseFactory(BusinessLineFactory::class)]
class BusinessLine extends Model
{
    /** @use HasFactory<BusinessLineFactory> */
    use Auditable, HasFactory, IsLookup;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_employee_id');
    }
}
