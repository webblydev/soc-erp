<?php

namespace App\Modules\Projects\Models;

use App\Modules\Catalog\Models\Service;
use App\Support\AuditTrail\Auditable;
use App\Support\AuditTrail\TracksAuthors;
use Database\Factories\Projects\TaskTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A reusable set of tasks for a kind of job (docs/04 §3.9, §6.3).
 *
 * @property int $id
 * @property string $name
 * @property int|null $service_id
 * @property int|null $project_type_id
 * @property bool $is_active
 * @property-read Service|null $service
 * @property-read ProjectType|null $projectType
 */
#[Fillable(['name', 'service_id', 'project_type_id', 'is_active'])]
#[UseFactory(TaskTemplateFactory::class)]
class TaskTemplate extends Model
{
    /** @use HasFactory<TaskTemplateFactory> */
    use Auditable, HasFactory, SoftDeletes, TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<ProjectType, $this>
     */
    public function projectType(): BelongsTo
    {
        return $this->belongsTo(ProjectType::class);
    }

    /**
     * @return HasMany<TaskTemplateItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(TaskTemplateItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
