<?php

namespace App\Modules\Foundation\Models;

use App\Support\AuditTrail\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $group
 * @property string $key
 * @property mixed $value
 * @property string $type
 * @property string $label
 * @property string|null $help
 * @property int|null $updated_by
 */
#[Fillable(['group', 'key', 'value', 'type', 'label', 'help', 'updated_by'])]
class Setting extends Model
{
    use Auditable;

    public const CREATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
