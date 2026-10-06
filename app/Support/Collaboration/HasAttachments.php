<?php

namespace App\Support\Collaboration;

use App\Modules\Foundation\Models\Attachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Polymorphic attachments (docs/01 §3.9). Models using it implement Collaborative.
 */
trait HasAttachments
{
    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
