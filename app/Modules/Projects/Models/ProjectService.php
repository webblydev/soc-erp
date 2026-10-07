<?php

namespace App\Modules\Projects\Models;

use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\Unit;
use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\TracksAuthors;
use Database\Factories\Projects\ProjectServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A service sold on a project (docs/04 §3.3). Amount = round(qty × rate) − discount (spec P5).
 *
 * @property int $id
 * @property int $project_id
 * @property int $service_id
 * @property string|null $description
 * @property string $quantity
 * @property int|null $unit_id
 * @property string $rate
 * @property string $discount_amount
 * @property string $amount
 * @property int $project_service_status_id
 * @property Carbon|null $delivered_on
 * @property int $sort_order
 * @property-read Service $service
 * @property-read ProjectServiceStatus $status
 */
#[Fillable(['service_id', 'description', 'quantity', 'unit_id', 'rate', 'discount_amount', 'amount', 'project_service_status_id', 'delivered_on', 'sort_order'])]
#[UseFactory(ProjectServiceFactory::class)]
class ProjectService extends Model
{
    /** @use HasFactory<ProjectServiceFactory> */
    use Auditable, HasFactory, TracksAuthors;

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
            'delivered_on' => 'date',
            'sort_order' => 'integer',
        ];
    }

    public function isCancelled(): bool
    {
        return $this->status->code === ProjectServiceStatus::CANCELLED;
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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
