<?php

namespace App\Modules\Hrm\Models;

use App\Modules\Hrm\Concerns\HasCodeLookup;
use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\IsLookup;
use Database\Factories\Hrm\EmployeeStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Employment status (docs/09 §3.1). is_active_employment and is_exit are set by the seeder on system rows only (spec H6).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $color
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_system
 * @property bool $is_active_employment
 * @property bool $is_exit
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system', 'is_active_employment', 'is_exit'])]
#[UseFactory(EmployeeStatusFactory::class)]
class EmployeeStatus extends Model
{
    /** @use HasFactory<EmployeeStatusFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    public const ACTIVE = 'ACTIVE';

    public const ON_LEAVE = 'ON_LEAVE';

    public const SUSPENDED = 'SUSPENDED';

    public const RESIGNED = 'RESIGNED';

    public const TERMINATED = 'TERMINATED';

    public const RETIRED = 'RETIRED';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active_employment' => 'boolean', 'is_exit' => 'boolean'];
    }
}
