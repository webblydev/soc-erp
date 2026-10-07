<?php

namespace App\Modules\Projects\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A document or step needed for an approval (docs/04 §3.10).
 *
 * @property int $id
 * @property int $project_approval_id
 * @property string $title
 * @property bool $is_done
 * @property Carbon|null $done_at
 * @property int|null $attachment_id
 * @property int $sort_order
 */
#[Fillable(['title', 'is_done', 'done_at', 'attachment_id', 'sort_order'])]
class ApprovalChecklistItem extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_done' => 'boolean', 'done_at' => 'datetime', 'sort_order' => 'integer'];
    }
}
