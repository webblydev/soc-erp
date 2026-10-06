<?php

namespace App\Modules\Crm\Models;

use App\Modules\Catalog\Models\Service;
use App\Support\AuditTrail\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A service a lead is interested in (docs/03 §3.5).
 *
 * @property int $id
 * @property int $lead_id
 * @property int $service_id
 * @property string|null $estimated_value
 * @property string|null $notes
 * @property-read Lead $lead
 * @property-read Service $service
 */
#[Fillable(['lead_id', 'service_id', 'estimated_value', 'notes'])]
class LeadServiceLine extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'lead_services';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['estimated_value' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
