<?php

namespace App\Modules\Crm\Actions;

use App\Models\User;
use App\Modules\Crm\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Bulk change of account manager (docs/03 §5.9). Customers the actor may not update are skipped.
 */
class ReassignCustomers
{
    /**
     * @param  list<int>  $ids
     */
    public function handle(User $actor, array $ids, ?int $userId): int
    {
        Validator::make(['user_id' => $userId], [
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)->whereNull('deleted_at')],
        ])->validate();

        return DB::transaction(function () use ($actor, $ids, $userId): int {
            $changed = 0;

            foreach (Customer::query()->whereKey($ids)->get() as $customer) {
                if ($actor->can('update', $customer) && $customer->account_manager_user_id !== $userId) {
                    $customer->update(['account_manager_user_id' => $userId]);
                    $changed++;
                }
            }

            return $changed;
        });
    }
}
