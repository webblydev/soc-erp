<?php

namespace App\Modules\Foundation\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

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
class NumberSequenceFormat extends Model {}
