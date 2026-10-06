<?php

namespace App\Modules\Hrm\Models;

use App\Support\AuditTrail\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A previous job of an employee (docs/09 §3.5).
 *
 * @property int $id
 * @property int $employee_id
 * @property string $company
 * @property string $position
 * @property Carbon|null $from_date
 * @property Carbon|null $to_date
 * @property string|null $notes
 */
#[Fillable(['company', 'position', 'from_date', 'to_date', 'notes'])]
class EmployeeExperience extends Model
{
    use Auditable;

    protected $table = 'employee_experience';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['from_date' => 'date', 'to_date' => 'date'];
    }
}
