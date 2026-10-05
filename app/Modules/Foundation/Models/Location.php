<?php

namespace App\Modules\Foundation\Models;

use App\Support\AuditTrail\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property int $location_level_id
 * @property string $name
 * @property string|null $name_bn
 * @property string $full_path
 * @property bool $is_active
 * @property-read LocationLevel $level
 * @property-read Location|null $parent
 */
#[Fillable(['parent_id', 'location_level_id', 'name', 'name_bn', 'full_path', 'is_active'])]
class Location extends Model
{
    use Auditable;

    public const PATH_SEPARATOR = ' › ';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'parent_id');
    }

    /**
     * @return HasMany<Location, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Location::class, 'parent_id')->orderBy('name');
    }

    /**
     * @return BelongsTo<LocationLevel, $this>
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(LocationLevel::class, 'location_level_id');
    }
}
