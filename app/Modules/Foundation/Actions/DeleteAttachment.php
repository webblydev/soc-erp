<?php

namespace App\Modules\Foundation\Actions;

use App\Models\User;
use App\Modules\Foundation\Models\Attachment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Soft-deletes an attachment; the file stays on disk (docs/01 §5.14).
 */
class DeleteAttachment
{
    /**
     * @throws AuthorizationException
     */
    public function handle(Attachment $attachment, User $actor): void
    {
        if (! self::allows($attachment, $actor)) {
            throw new AuthorizationException;
        }

        DB::transaction(fn () => $attachment->delete());
    }

    /**
     * attachments.delete_any, or delete_own for the uploader within 24 hours, and the parent visible.
     */
    public static function allows(Attachment $attachment, User $actor): bool
    {
        if ($attachment->attachable === null || ! $attachment->attachable->isViewableBy($actor)) {
            return false;
        }

        if ($actor->can('attachments.delete_any')) {
            return true;
        }

        return $actor->can('attachments.delete_own')
            && $attachment->uploaded_by === $actor->id
            && $attachment->created_at->greaterThan(now()->subHours(Attachment::DELETE_OWN_HOURS));
    }
}
