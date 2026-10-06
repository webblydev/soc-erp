<?php

namespace App\Modules\Crm\Actions;

use App\Models\User;
use App\Modules\Crm\Events\CustomersMerging;
use App\Modules\Crm\Models\CrmActivity;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\CustomerContact;
use App\Modules\Crm\Models\Lead;
use App\Modules\Foundation\Models\Attachment;
use App\Modules\Foundation\Models\Note;
use App\Support\AuditTrail\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Merges a duplicate customer into a survivor (docs/03 §5.12, spec R16). The shared attachments
 * and notes rows are the one place CRM writes Foundation tables: they belong to the customer.
 */
class MergeCustomers
{
    /**
     * @return array<string, int>
     */
    public function preview(Customer $duplicate): array
    {
        return array_map(fn (Builder $query): int => $query->count(), $this->queries($duplicate));
    }

    /**
     * @throws ValidationException
     */
    public function handle(User $actor, Customer $survivor, Customer $duplicate, string $reason): Customer
    {
        Gate::forUser($actor)->authorize('merge', $survivor);
        Gate::forUser($actor)->authorize('merge', $duplicate);

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => __('Give a reason for the merge.')]);
        }

        if ($survivor->is($duplicate) || $survivor->merged_into_id !== null || $duplicate->merged_into_id !== null) {
            throw ValidationException::withMessages(['duplicate' => __('Choose two different customers that are not merged already.')]);
        }

        return DB::transaction(function () use ($actor, $survivor, $duplicate, $reason): Customer {
            $survivorHasPrimary = $survivor->contacts()->where('is_primary', true)->exists();
            $moves = [
                'leads' => ['converted_customer_id' => $survivor->id],
                'referred_leads' => ['referrer_id' => $survivor->id],
                'activities' => ['subject_id' => $survivor->id],
                'contacts' => ['customer_id' => $survivor->id, ...($survivorHasPrimary ? ['is_primary' => false] : [])],
                'attachments' => ['attachable_id' => $survivor->id],
                'notes' => ['notable_id' => $survivor->id],
            ];

            foreach ($this->queries($duplicate) as $table => $query) {
                $count = $query->toBase()->update($moves[$table]);

                if ($count > 0) {
                    AuditTrail::record($survivor, 'merged', null, ['table' => $table, 'count' => $count, 'from' => $duplicate->customer_number, 'reason' => $reason], $actor);
                }
            }

            $this->carryAttribution($survivor, $duplicate);

            CustomersMerging::dispatch($survivor, $duplicate);

            $duplicate->forceFill(['merged_into_id' => $survivor->id])->save();
            $duplicate->delete();

            return $survivor->refresh();
        });
    }

    /**
     * Rows that point at the duplicate, keyed like preview().
     *
     * @return array<string, Builder<covariant Model>>
     */
    private function queries(Customer $duplicate): array
    {
        $alias = $duplicate->getMorphClass();

        return [
            'leads' => Lead::withTrashed()->where('converted_customer_id', $duplicate->id),
            'referred_leads' => Lead::withTrashed()->where('referrer_type', 'customer')->where('referrer_id', $duplicate->id),
            'activities' => CrmActivity::withTrashed()->where('subject_type', $alias)->where('subject_id', $duplicate->id),
            'contacts' => CustomerContact::withTrashed()->where('customer_id', $duplicate->id),
            'attachments' => Attachment::withTrashed()->where('attachable_type', $alias)->where('attachable_id', $duplicate->id),
            'notes' => Note::withTrashed()->where('notable_type', $alias)->where('notable_id', $duplicate->id),
        ];
    }

    /**
     * Keeps first-lead attribution (CRM-BR-16): the earlier source lead wins.
     */
    private function carryAttribution(Customer $survivor, Customer $duplicate): void
    {
        $useDuplicate = $duplicate->source_lead_id !== null
            && ($survivor->source_lead_id === null || $duplicate->source_lead_id < $survivor->source_lead_id);

        if ($useDuplicate) {
            $survivor->forceFill([
                'source_lead_id' => $duplicate->source_lead_id,
                'lead_source_id' => $duplicate->lead_source_id,
                'acquired_by_user_id' => $duplicate->acquired_by_user_id,
            ])->save();
        }
    }
}
