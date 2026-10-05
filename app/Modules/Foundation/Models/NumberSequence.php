<?php

namespace App\Modules\Foundation\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $document_type
 * @property string $scope_key
 * @property string $format
 * @property int $next_number
 * @property string $reset_policy
 */
#[Fillable(['document_type', 'scope_key', 'format', 'next_number', 'reset_policy'])]
class NumberSequence extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['next_number' => 'integer'];
    }
}
