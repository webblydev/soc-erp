<?php

namespace App\Modules\Projects\Models;

use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\Unit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A proposed service line of an amendment; project_service_id is null for a new line (spec P11).
 *
 * @property int $id
 * @property int $project_contract_amendment_id
 * @property int|null $project_service_id
 * @property int $service_id
 * @property string|null $description
 * @property string $quantity
 * @property int|null $unit_id
 * @property string $rate
 * @property string $discount_amount
 * @property string $amount
 * @property int $project_service_status_id
 * @property int $sort_order
 * @property-read Service $service
 * @property-read ProjectServiceStatus $status
 */
#[Fillable(['project_service_id', 'service_id', 'description', 'quantity', 'unit_id', 'rate', 'discount_amount', 'amount', 'project_service_status_id', 'sort_order'])]
class ProjectContractAmendmentLine extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'rate' => 'decimal:4',
            'discount_amount' => 'decimal:2',
            'amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<ProjectServiceStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(ProjectServiceStatus::class, 'project_service_status_id');
    }
}
