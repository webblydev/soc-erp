<?php

namespace App\Modules\Catalog\Models;

use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\IsLookup;
use Database\Factories\Catalog\PricingBasisFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * How a service is priced (docs/02 §3.3). Code checks the row code.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 * @property bool $is_system
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system'])]
#[UseFactory(PricingBasisFactory::class)]
class PricingBasis extends Model
{
    /** @use HasFactory<PricingBasisFactory> */
    use Auditable, HasFactory, IsLookup;

    public const FIXED = 'fixed';

    public const PER_UNIT = 'per_unit';

    public const PERCENT_OF_COST = 'percent_of_cost';

    protected $table = 'pricing_bases';
}
