<?php

namespace App\Support\AuditTrail;

use App\Http\Middleware\HandleImpersonation;
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
            'impersonator_id' => self::impersonatorId(),
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
     * The super admin signed in as the current user, when the session holds one.
     */
    private static function impersonatorId(): ?int
    {
        if (! app()->bound('session')) {
            return null;
        }

        $impersonatorId = session(HandleImpersonation::SESSION_KEY);

        return is_numeric($impersonatorId) ? (int) $impersonatorId : null;
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
