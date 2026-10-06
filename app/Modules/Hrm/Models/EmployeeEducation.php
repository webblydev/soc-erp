<?php

namespace App\Modules\Hrm\Models;

use App\Support\AuditTrail\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A degree or course an employee completed (docs/09 §3.5).
 *
 * @property int $id
 * @property int $employee_id
 * @property string $institution
 * @property string $degree
 * @property int|null $from_year
 * @property int|null $to_year
 * @property string|null $result
 */
#[Fillable(['institution', 'degree', 'from_year', 'to_year', 'result'])]
class EmployeeEducation extends Model
{
    use Auditable;

    protected $table = 'employee_education';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['from_year' => 'integer', 'to_year' => 'integer'];
    }
}
