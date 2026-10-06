<?php

namespace App\Modules\Crm\Actions;

use App\Models\User;
use App\Modules\Crm\Events\LeadConverted;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\LeadStatus;
use App\Modules\Crm\Notifications\LeadWon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Converts a won lead into a customer (docs/03 §5.7, CRM-BR-09). The project step arrives with
 * 04 (spec R1); until then conversion is customer + lead update in one transaction.
 */
class ConvertLead
{
    public function __construct(private SaveCustomer $saveCustomer) {}

    /**
     * @param  array{customer_id?: int|string|null, customer?: array<string, mixed>}  $input
     *
     * @throws ValidationException
     */
    public function handle(User $actor, Lead $lead, array $input): Customer
    {
        Gate::forUser($actor)->authorize('convert', $lead);
        LeadState::ensureOpen($lead);

        $linked = $this->linkedCustomer($input);
        $attribution = [
            'source_lead_id' => $lead->id,
            'lead_source_id' => $lead->lead_source_id,
            'acquired_by_user_id' => $lead->assigned_to ?? $actor->id,
        ];

        $customer = DB::transaction(function () use ($actor, $lead, $input, $linked, $attribution): Customer {
            if ($linked !== null) {
                $customer = $linked;

                // First-lead attribution stays with the customer (CRM-BR-16); only gaps are filled.
                foreach ($attribution as $column => $value) {
                    $customer->{$column} ??= $value;
                }

                $customer->save();
            } else {
                $customer = $this->saveCustomer->handle($actor, $input['customer'] ?? [], null, $attribution);
            }

            LeadState::recordStatus($actor, $lead, LeadStatus::idFor(LeadStatus::WON), __('Converted to :number', ['number' => $customer->customer_number]), [
                'won_at' => now(),
                'converted_customer_id' => $customer->id,
                'converted_at' => now(),
                'converted_by' => $actor->id,
            ]);

            LeadConverted::dispatch($lead, $customer);

            return $customer;
        });

        Notification::send($this->wonRecipients($lead), new LeadWon($lead, $customer));

        return $customer;
    }

    /**
     * @param  array{customer_id?: int|string|null}  $input
     *
     * @throws ValidationException
     */
    private function linkedCustomer(array $input): ?Customer
    {
        if (empty($input['customer_id'])) {
            return null;
        }

        $customer = Customer::query()->whereNull('merged_into_id')->with('status')->find((int) $input['customer_id']);

        if ($customer === null || $customer->isBlocked()) {
            throw ValidationException::withMessages(['customer_id' => __('Choose an active customer that is not blocked.')]);
        }

        return $customer;
    }

    /**
     * The lead's team manager and every active management user (spec R14).
     *
     * @return Collection<int, User>
     */
    private function wonRecipients(Lead $lead): Collection
    {
        $managerId = $lead->team?->manager_user_id;

        return User::query()->where('is_active', true)
            ->where(fn ($query) => $query->whereHas('roles', fn ($query) => $query->where('code', 'management'))
                ->when($managerId !== null, fn ($query) => $query->orWhere('id', $managerId)))
            ->get();
    }
}
