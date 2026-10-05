<?php

namespace App\Support\AuditTrail;

use App\Models\User;
use App\Modules\Foundation\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

final class AuditTrail
{
    /**
     * Write an audit_logs row for the model (CM-BR-01).
     *
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public static function record(Model $model, string $event, ?array $old = null, ?array $new = null, ?User $actor = null): AuditLog
    {
        $request = app()->bound('request') ? request() : null;

        return AuditLog::query()->create([
            'user_id' => $actor !== null ? $actor->id : Auth::id(),
            'event' => $event,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'url' => $request ? Str::limit($request->fullUrl(), 497) : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 252) : null,
        ]);
    }

    /**
     * Remove attributes that must never be audited.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function filter(Model $model, array $attributes): array
    {
        $excluded = method_exists($model, 'auditExcludedAttributes')
            ? $model->auditExcludedAttributes()
            : $model->getHidden();

        return Arr::except($attributes, $excluded);
    }
}
