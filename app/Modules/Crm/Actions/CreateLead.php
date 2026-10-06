<?php

namespace App\Modules\Crm\Actions;

use App\Models\User;
use App\Modules\Crm\Concerns\ValidatesLeadInput;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\LeadStatus;
use App\Modules\Crm\Models\SalesTeam;
use App\Modules\Crm\Services\LeadFollowUps;
use App\Modules\Crm\Services\RoundRobinAssigner;
use App\Support\Facades\Settings;
use App\Support\Lookups\ActiveLookup;
use App\Support\NumberSequenceService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Captures a new enquiry (docs/03 §5.2): number, NEW status, services, assignment, first
 * follow-up and the duplicate check (CRM-BR-01–06, spec R9, R10).
 */
class CreateLead
{
    use ValidatesLeadInput;

    public function __construct(
        private NumberSequenceService $numbers,
        private FindDuplicates $findDuplicates,
        private AssignLead $assignLead,
        private RoundRobinAssigner $roundRobin,
        private LogActivity $logActivity,
        private LeadFollowUps $followUps,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(User $actor, array $input): Lead
    {
        Gate::forUser($actor)->authorize('create', Lead::class);

        $data = $this->validateLead($this->normaliseLead($input), null, [
            'assigned_to' => ['nullable', 'integer'],
            'sales_team_id' => ['nullable', 'integer', Rule::exists('sales_teams', 'id')->where('is_active', true)],
            'follow_up' => ['nullable', 'array'],
            'follow_up.activity_type_id' => ['required_with:follow_up', new ActiveLookup('activity_types')],
            'follow_up.scheduled_at' => ['required_with:follow_up', 'date', 'after:now'],
            'duplicate_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $matches = $this->guardDuplicates($this->findDuplicates, $data);
        [$assignee, $systemPick] = $this->assignee($data);

        return DB::transaction(function () use ($actor, $data, $assignee, $systemPick, $matches): Lead {
            $lead = new Lead(Arr::only($data, (new Lead)->getFillable()));
            $lead->forceFill([
                'lead_number' => $this->numbers->next('lead'),
                'lead_status_id' => LeadStatus::idFor(LeadStatus::NEW),
                'expected_value' => $this->expectedValue($data),
                'sales_team_id' => $data['sales_team_id'] ?? null,
            ])->save();

            $this->syncServices($lead, $data['services']);

            $lead->statusHistories()->create([
                'from_status_id' => null,
                'to_status_id' => $lead->lead_status_id,
                'changed_by' => $actor->id,
                'changed_at' => now(),
            ]);

            if ($assignee !== null) {
                $this->assignLead->assign($actor, $lead, $assignee, null, $systemPick);
            }

            if (! empty($data['follow_up'])) {
                $this->logActivity->scheduleNext($lead, $assignee ?? $actor->id, $data['follow_up']);
                $this->followUps->refresh($lead);
            }

            $this->auditDuplicateOverride($actor, $lead, $matches, $data['duplicate_reason'] ?? null);

            return $lead->refresh();
        });
    }

    /**
     * The assignee and whether the system picked it (round-robin, spec R9).
     *
     * @param  array<string, mixed>  $data
     * @return array{0: ?int, 1: bool}
     */
    private function assignee(array $data): array
    {
        if (! empty($data['assigned_to'])) {
            return [(int) $data['assigned_to'], false];
        }

        if (Settings::get('crm.auto_assign_mode') === 'round_robin_team' && ! empty($data['sales_team_id'])) {
            return [$this->roundRobin->pick(SalesTeam::query()->findOrFail((int) $data['sales_team_id'])), true];
        }

        return [null, false];
    }
}
