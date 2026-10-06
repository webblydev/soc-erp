<?php

namespace App\Modules\Catalog\Models;

use App\Models\User;
use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\TracksAuthors;
use App\Support\Collaboration\Collaborative;
use Database\Factories\Catalog\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Something SOC sells (docs/02 §3.3). Account, VAT and task template columns are filled by the
 * modules that own those tables (spec C1).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $service_category_id
 * @property int|null $business_line_id
 * @property int|null $default_unit_id
 * @property string|null $default_rate
 * @property int $pricing_basis_id
 * @property bool $requires_approval_tracking
 * @property string|null $description
 * @property bool $is_active
 * @property int|null $created_by
 * @property-read ServiceCategory $category
 * @property-read BusinessLine|null $businessLine
 * @property-read Unit|null $defaultUnit
 * @property-read PricingBasis $pricingBasis
 */
#[Fillable(['code', 'name', 'service_category_id', 'business_line_id', 'default_unit_id', 'default_rate', 'pricing_basis_id', 'requires_approval_tracking', 'description', 'is_active'])]
#[UseFactory(ServiceFactory::class)]
class Service extends Model implements Collaborative
{
    /** @use HasFactory<ServiceFactory> */
    use Auditable, HasFactory, SoftDeletes, TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_rate' => 'decimal:4',
            'requires_approval_tracking' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function isViewableBy(User $user): bool
    {
        return $user->can('catalog.services.view');
    }

    /**
     * @return BelongsTo<ServiceCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    /**
     * @return BelongsTo<BusinessLine, $this>
     */
    public function businessLine(): BelongsTo
    {
        return $this->belongsTo(BusinessLine::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function defaultUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'default_unit_id');
    }

    /**
     * @return BelongsTo<PricingBasis, $this>
     */
    public function pricingBasis(): BelongsTo
    {
        return $this->belongsTo(PricingBasis::class);
    }
}
