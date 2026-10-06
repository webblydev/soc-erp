<?php

namespace App\Modules\Foundation\Models;

use App\Support\AuditTrail\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Default number format per document type (docs/00 §5).
 *
 * @property int $id
 * @property string $document_type
 * @property string $format
 * @property string $reset_policy never|fiscal_year
 * @property string|null $scope_by business_line|branch
 */
#[Fillable(['document_type', 'format', 'reset_policy', 'scope_by'])]
class NumberSequenceFormat extends Model
{
    use Auditable;

    /**
     * @return HasMany<NumberSequence, $this>
     */
    public function sequences(): HasMany
    {
        return $this->hasMany(NumberSequence::class, 'document_type', 'document_type')->orderBy('scope_key');
    }
}
