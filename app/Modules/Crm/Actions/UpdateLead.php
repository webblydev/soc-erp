<?php

namespace App\Modules\Crm\Actions;

use App\Models\User;
use App\Modules\Crm\Concerns\ValidatesLeadInput;
use App\Modules\Crm\Models\Lead;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Edits an open or lost lead's fields and services (docs/03 §5.2). Status, assignment and
 * conversion have their own Actions; converted leads are read-only (CRM-BR-10). The duplicate
 * check runs only when a phone or the email changed, so an earlier override is not asked again.
 */
class UpdateLead
{
    use ValidatesLeadInput;

    public function __construct(private FindDuplicates $findDuplicates) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $actor, Lead $lead, array $input): Lead
    {
        if ($lead->isConverted()) {
            throw ValidationException::withMessages(['lead' => __('Converted leads are read-only.')]);
        }

        Gate::forUser($actor)->authorize('update', $lead);

        $data = $this->validateLead($this->normaliseLead($input), $lead, [
            'duplicate_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $contactFields = ['phone', 'whatsapp', 'office_phone', 'email'];
        $contactsChanged = array_map(fn (string $field): mixed => $data[$field] ?? null, $contactFields)
            !== array_map(fn (string $field): mixed => $lead->getAttribute($field), $contactFields);
        $matches = $contactsChanged ? $this->guardDuplicates($this->findDuplicates, $data, $lead) : [];

        return DB::transaction(function () use ($actor, $lead, $data, $matches): Lead {
            $lead->fill(Arr::only($data, $lead->getFillable()));
            $lead->expected_value = $this->expectedValue($data);
            $lead->save();

            $this->syncServices($lead, $data['services']);
            $this->auditDuplicateOverride($actor, $lead, $matches, $data['duplicate_reason'] ?? null);

            return $lead->refresh();
        });
    }
}
