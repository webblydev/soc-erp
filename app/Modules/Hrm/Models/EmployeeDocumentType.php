<?php

namespace App\Modules\Hrm\Models;

use App\Modules\Hrm\Concerns\HasCodeLookup;
use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\IsLookup;
use Database\Factories\Hrm\EmployeeDocumentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Kind of employee document (docs/09 §3.1); has_expiry makes the expiry date required (spec H13).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $color
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_system
 * @property bool $has_expiry
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system', 'has_expiry'])]
#[UseFactory(EmployeeDocumentTypeFactory::class)]
class EmployeeDocumentType extends Model
{
    /** @use HasFactory<EmployeeDocumentTypeFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['has_expiry' => 'boolean'];
    }
}
