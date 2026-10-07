<?php

namespace App\Modules\Crm\Actions;

use App\Models\User;
use App\Modules\Crm\Events\LeadConverted;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\LeadStatus;
use App\Modules\Crm\Notifications\LeadWon;
use App\Modules\Projects\Actions\CreateProject;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectStatus;
use App\Support\Facades\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Converts a won lead into a customer and a project (docs/03 §5.7, CRM-BR-09, Projects spec P21):
 * customer, project (through the Projects CreateProject action) and lead update in one
 * transaction, so a failing project leaves no customer and the lead unchanged (CRM-AC-06).
 * The project can be skipped only when crm.allow_convert_without_project is on.
 */
class ConvertLead
{
    public function __construct(private SaveCustomer $saveCustomer, private CreateProject $createProject) {}

    /**
     * @param  array{customer_id?: int|string|null, customer?: array<string, mixed>, project?: array<string, mixed>|null, skip_project?: bool}  $input
     *
     * @throws ValidationException
     */
    public function handle(User $actor, Lead $lead, array $input): Customer
    {
        Gate::forUser($actor)->authorize('convert', $lead);
        LeadState::ensureOpen($lead);

        $skipProject = (bool) ($input['skip_project'] ?? false);

        if ($skipProject && ! Settings::get('crm.allow_convert_without_project', false)) {
            throw ValidationException::withMessages(['project' => __('A won lead needs a project.')]);
        }

        $linked = $this->linkedCustomer($input);
        $attribution = [
            'source_lead_id' => $lead->id,
            'lead_source_id' => $lead->lead_source_id,
            'acquired_by_user_id' => $lead->assigned_to ?? $actor->id,
        ];

        $customer = DB::transaction(function () use ($actor, $lead, $input, $linked, $attribution, $skipProject): Customer {
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

            $project = $skipProject ? null : $this->createProjectFor($actor, $lead, $customer, is_array($input['project'] ?? null) ? $input['project'] : []);
            $note = $project !== null
                ? __('Converted to :customer and project :project', ['customer' => $customer->customer_number, 'project' => $project->project_number])
                : __('Converted to :number', ['number' => $customer->customer_number]);

            LeadState::recordStatus($actor, $lead, LeadStatus::idFor(LeadStatus::WON), $note, [
                'won_at' => now(),
                'converted_customer_id' => $customer->id,
                'converted_project_id' => $project?->id,
                'converted_at' => now(),
                'converted_by' => $actor->id,
            ]);

            LeadConverted::dispatch($lead, $customer, $project);

            return $customer;
        });

        Notification::send($this->wonRecipients($lead), new LeadWon($lead, $customer));

        return $customer;
    }

    /**
     * The project of the conversion. Its validation errors come back under `project.` so they do
     * not mix with the customer's fields.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    private function createProjectFor(User $actor, Lead $lead, Customer $customer, array $input): Project
    {
        $statusCode = (string) Settings::get('projects.default_status_on_conversion', ProjectStatus::CONTRACTED);

        try {
            return $this->createProject->handle($actor, [
                ...$input,
                'customer_id' => $customer->id,
                'source_lead_id' => $lead->id,
                'project_status_id' => ProjectStatus::query()->where('code', $statusCode)->value('id') ?? ProjectStatus::idFor(ProjectStatus::CONTRACTED),
            ], viaConversion: true);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => ["project.{$key}" => $messages])->all());
        }
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
