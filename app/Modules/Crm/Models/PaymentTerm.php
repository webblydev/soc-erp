<?php

namespace App\Modules\Crm\Models;

use App\Modules\Crm\Concerns\HasCodeLookup;
use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\IsLookup;
use Database\Factories\Crm\PaymentTermFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A customer payment term in days (docs/03 §3.1).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $color
 * @property int $sort_order
 * @property int $days
 * @property bool $is_active
 * @property bool $is_system
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system', 'days'])]
#[UseFactory(PaymentTermFactory::class)]
class PaymentTerm extends Model
{
    /** @use HasFactory<PaymentTermFactory> */
    use Auditable, HasCodeLookup, HasFactory, IsLookup;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['days' => 'integer'];
    }
}
