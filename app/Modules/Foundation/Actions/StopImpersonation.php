<?php

namespace App\Modules\Foundation\Actions;

use App\Http\Middleware\HandleImpersonation;
use App\Models\User;
use App\Support\AuditTrail\AuditTrail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StopImpersonation
{
    /**
     * Sign back in as the impersonator. The session key is removed only after the switch, so
     * the Login listener still treats the switch as part of impersonation and skips its audit.
     */
    public function handle(): ?User
    {
        $impersonatorId = session(HandleImpersonation::SESSION_KEY);
        $target = Auth::user();

        if ($impersonatorId === null || ! $target instanceof User) {
            return null;
        }

        $impersonator = User::query()->whereKey($impersonatorId)->first();

        if ($impersonator === null) {
            Auth::guard('web')->logout();
            session()->forget(HandleImpersonation::SESSION_KEY);

            return null;
        }

        DB::transaction(function () use ($impersonator, $target): void {
            AuditTrail::record($target, 'impersonation_ended', null, ['impersonator_id' => $impersonator->id, 'user_id' => $target->id], $impersonator);
        });

        Auth::guard('web')->login($impersonator);
        session()->forget(HandleImpersonation::SESSION_KEY);

        return $impersonator;
    }
}
