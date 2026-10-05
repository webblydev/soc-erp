<?php

namespace App\Modules\Foundation\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Seeded from module manifests only; never edited in the UI.
 *
 * @property int $id
 * @property string $name
 * @property string $module
 * @property string $resource
 * @property string $action
 * @property string|null $label
 * @property int $sort_order
 */
#[Fillable(['name', 'module', 'resource', 'action', 'label', 'sort_order'])]
class Permission extends Model
{
    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }
}
