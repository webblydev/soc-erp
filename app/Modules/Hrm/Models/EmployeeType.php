<?php

namespace App\Modules\Hrm\Models;

use App\Modules\Hrm\Concerns\HasCodeLookup;
use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\IsLookup;
use Database\Factories\Hrm\EmployeeTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Employment type (docs/09 §3.1). PROBATION drives the probation reminder (spec H14).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $color
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_system
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system'])]
#[UseFactory(EmployeeTypeFactory::class)]
class EmployeeType extends Model
{
    /** @use HasFactory<EmployeeTypeFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    public const PROBATION = 'PROBATION';
}
