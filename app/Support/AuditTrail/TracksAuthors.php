<?php

namespace App\Support\AuditTrail;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait TracksAuthors
{
    public static function bootTracksAuthors(): void
    {
        static::creating(function (Model $model): void {
            $userId = Auth::id();

            if ($userId === null) {
                return;
            }

            $model->setAttribute('created_by', $model->getAttribute('created_by') ?? $userId);
            $model->setAttribute('updated_by', $model->getAttribute('updated_by') ?? $userId);
        });

        static::updating(function (Model $model): void {
            if (Auth::id() !== null) {
                $model->setAttribute('updated_by', Auth::id());
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
