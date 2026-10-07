<?php

namespace Database\Seeders\Legacy;

use App\Modules\Catalog\Models\BusinessLine;
use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\CustomerStatus;
use App\Modules\Crm\Models\CustomerType;
use App\Modules\Crm\Models\Lead;
use App\Modules\Crm\Models\LeadServiceLine;
use App\Modules\Foundation\Models\CompanyProfile;
use App\Modules\Foundation\Models\NumberSequence;
use App\Modules\Foundation\Models\NumberSequenceFormat;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectRole;
use App\Modules\Projects\Models\ProjectServiceStatus;
use App\Modules\Projects\Models\ProjectStatus;
use App\Modules\Projects\Models\ProjectType;
use App\Support\NumberSequenceService;
use App\Support\Phone;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * v1 projects (tbl_project) → projects with customer, people, team and services (legacy seed
 * spec L13, L14, docs/11 §4.4). Deleted and test rows are skipped; imported rows are not touched again.
 */
class ImportProjects
{
    /**
     * v1 project numbers that are test data.
     */
    private const TEST_NUMBERS = ['01111'];

    /** @var Collection<string, BusinessLine> */
    private Collection $lines;

    /** @var array<string, true> */
    private array $usedNumbers = [];

    /** @var Collection<int|string, Collection<int, stdClass>> legacy project id → its v1 tasks */
    private Collection $tasks;

    /** @var array<int, object> legacy project id → first v1 client pointing at it */
    private array $clients = [];

    /** @var array<string, int> v1 client code → first v1 client id */
    private array $clientCodes = [];

    /** @var array<int, string|null> employee id → department code */
    private array $departments = [];

    /** @var array<string, list<int>> normalised name → v1 customer ids */
    private array $customerNames = [];

    public function __construct(private NumberSequenceService $numbers) {}

    public function run(LegacyContext $context): int
    {
        $created = 0;
        $legacy = $context->legacy();

        $this->lines = BusinessLine::query()->get()->keyBy('code');
        $this->usedNumbers = array_fill_keys(Project::withTrashed()->pluck('project_number')->all(), true);
        $this->tasks = $legacy->table('tbl_task')->where('projectId', '>', 0)->orderBy('id')
            ->get(['id', 'projectId', 'assign_to', 'support_id', 'entry_date', 'client_list_id', 'client_id', 'client_name', 'client_phone'])
            ->groupBy(fn (object $task): int => (int) $task->projectId);

        foreach ($legacy->table('tbl_client')->where('status', '<>', 'd')->orderBy('id')->get(['id', 'client_id', 'project_id']) as $client) {
            if ((int) $client->project_id > 0) {
                $this->clients[(int) $client->project_id] ??= $client;
            }

            if (trim((string) $client->client_id) !== '') {
                $this->clientCodes[trim((string) $client->client_id)] ??= (int) $client->id;
            }
        }

        foreach (Customer::query()->whereNotNull('legacy_client_ref')->get(['id', 'name', 'company_name']) as $customer) {
            foreach (array_unique(array_filter([self::normalisedName($customer->name), self::normalisedName($customer->company_name)])) as $key) {
                $this->customerNames[$key][] = $customer->id;
            }
        }

        $this->departments = Employee::query()->with('department:id,code')->get(['id', 'department_id'])
            ->mapWithKeys(fn (Employee $employee): array => [$employee->id => $employee->department->code])->all();

        foreach ($legacy->table('tbl_project')->where('status', '<>', 'd')->orderBy('id')->get() as $row) {
            if (in_array(trim((string) $row->project_id), self::TEST_NUMBERS, true)) {
                continue;
            }

            $existing = Project::withTrashed()->where('legacy_project_ref', $row->id)->value('id');

            if ($existing === null) {
                $existing = DB::transaction(fn (): int => $this->import($context, $row));
                $created++;
            }

            $context->projects[(int) $row->id] = (int) $existing;
        }

        $this->advanceSequences();

        return $created;
    }

    private function import(LegacyContext $context, object $row): int
    {
        $addedAt = LegacyMap::dateTime($row->add_time) ?? now();
        $tasks = $this->tasks->get((int) $row->id, collect());
        [$typeCode, $defaultLine] = LegacyMap::TYPES[(int) $row->project_type_id] ?? LegacyMap::DEFAULT_TYPE;
        [$number, $note] = $this->number((string) $row->project_id, (int) $row->id);

        $line = $this->lines->get(LegacyMap::projectBusinessLineFor($number) ?? $defaultLine) ?? $this->lines->get($defaultLine) ?? $this->lines->get('BD');
        $internal = $typeCode === 'INTERNAL' || ($line !== null && $line->is_internal);

        if ($internal) {
            $typeCode = 'INTERNAL';
            $line = $line?->is_internal ? $line : ($this->lines->get($defaultLine)?->is_internal ? $this->lines->get($defaultLine) : $this->lines->get('MGT'));
        }

        $leadId = isset($this->clients[(int) $row->id]) ? ($context->leads[(int) $this->clients[(int) $row->id]->id]['lead'] ?? null) : null;
        [$customerId, $customerNote] = $internal ? [null, null] : $this->customer($context, $row, $tasks, $addedAt);
        $note = trim(implode(' ', array_filter([$note, $customerNote]))) ?: null;
        $people = $this->people($context, $tasks);
        $ownerId = $context->users[(int) $row->add_by] ?? $context->fallbackUserId();
        $statusId = (int) $context->idFor(ProjectStatus::class, ProjectStatus::IN_PROGRESS);
        $firstTask = $tasks->map(fn (object $task): ?string => LegacyMap::date($task->entry_date))->filter()->min();

        $project = new Project;
        $project->forceFill([
            'project_number' => $number,
            'name' => trim((string) $row->name) !== '' ? trim((string) $row->name) : $number,
            'customer_id' => $customerId,
            'business_line_id' => $line?->id,
            'project_type_id' => $context->idFor(ProjectType::class, $typeCode),
            'project_status_id' => $statusId,
            'source_lead_id' => $leadId,
            'project_manager_id' => $people['manager'],
            'start_date' => $firstTask ?? $addedAt->toDateString(),
            'contract_value' => 0,
            'legacy_project_ids' => [(int) $row->id],
            'legacy_project_ref' => (int) $row->id,
            'notes' => $note,
            'created_by' => $ownerId,
            'updated_by' => $ownerId,
            'created_at' => $addedAt,
            'updated_at' => LegacyMap::dateTime($row->update_time) ?? $addedAt,
        ])->save();

        $project->statusHistories()->create(['to_status_id' => $statusId, 'reason' => 'Imported from v1', 'changed_by' => $ownerId, 'changed_at' => $addedAt]);

        foreach ($people['team'] as $employeeId => [$roleCode, $since]) {
            $project->team()->create([
                'employee_id' => $employeeId,
                'project_role_id' => $context->idFor(ProjectRole::class, $roleCode),
                'assigned_on' => $since ?? $project->start_date,
                'is_active' => true,
            ]);
        }

        if ($leadId !== null) {
            $this->copyServices($context, $project, $leadId);
            Lead::query()->whereKey($leadId)->whereNotNull('converted_customer_id')->whereNull('converted_project_id')->update(['converted_project_id' => $project->id]);
        }

        return $project->id;
    }

    /**
     * The trimmed v1 number; a number already used gets the v1 id appended and a note.
     *
     * @return array{0: string, 1: string|null}
     */
    private function number(string $value, int $legacyId): array
    {
        $number = mb_substr(trim($value), 0, 40);
        $note = null;

        if ($number === '' || isset($this->usedNumbers[$number])) {
            $note = $number === '' ? 'v1 project had no number.' : "v1 number {$number} is used by another v1 project; check which one keeps it.";
            $number = mb_substr(($number === '' ? 'V1-PROJECT' : $number), 0, 30).'-'.$legacyId;
        }

        $this->usedNumbers[$number] = true;

        return [$number, $note];
    }

    /**
     * The linked client's customer, else the most common customer of the project's tasks, else
     * the one v1 customer whose name matches the project name, else a new customer from the first
     * task's client (spec L14). The note flags a name match for review.
     *
     * @param  Collection<int, stdClass>  $tasks
     * @return array{0: int, 1: string|null}
     */
    private function customer(LegacyContext $context, object $row, Collection $tasks, CarbonInterface $addedAt): array
    {
        $client = $this->clients[(int) $row->id] ?? null;
        $customerId = $client !== null ? ($context->leads[(int) $client->id]['customer'] ?? null) : null;

        if ($customerId !== null) {
            return [$customerId, null];
        }

        $fromTasks = $tasks->map(function (object $task) use ($context): ?int {
            $clientId = (int) $task->client_list_id > 0 ? (int) $task->client_list_id : ($this->clientCodes[trim((string) $task->client_id)] ?? null);

            return $clientId !== null ? ($context->leads[$clientId]['customer'] ?? null) : null;
        })->filter()->countBy()->sortDesc()->keys()->first();

        if ($fromTasks !== null) {
            return [(int) $fromTasks, null];
        }

        $byName = array_values(array_unique($this->customerNames[self::normalisedName(preg_split('/[,(\-]/', (string) $row->name)[0] ?? '')] ?? []));

        if (count($byName) === 1) {
            return [$byName[0], 'Customer matched by name from v1; check it.'];
        }

        $task = $tasks->first();
        $phone = Phone::normalise(is_string($task?->client_phone) ? $task->client_phone : null);
        $phone = Phone::isValid($phone) ? $phone : (Phone::normalise(CompanyProfile::current()?->phone) ?? '');
        $name = trim((string) ($task !== null ? $task->client_name : ''));

        $customer = new Customer;
        $customer->forceFill([
            'customer_number' => $this->numbers->next('customer'),
            'customer_type_id' => $context->idFor(CustomerType::class, 'INDIVIDUAL'),
            'name' => $name !== '' && ! in_array(strtolower($name), ['n/a', 'na'], true) ? $name : trim((string) $row->name),
            'phone' => $phone,
            'customer_status_id' => $context->idFor(CustomerStatus::class, CustomerStatus::ACTIVE),
            'notes' => 'Created for v1 project '.trim((string) $row->project_id).'.',
            'created_at' => $addedAt,
            'updated_at' => $addedAt,
        ])->save();

        return [$customer->id, null];
    }

    /**
     * Lower-case letters of a name without titles (Md., Mr., Mrs. …); null when under 6 letters.
     */
    private static function normalisedName(?string $name): ?string
    {
        $key = (string) preg_replace('/[^a-z]/', '', strtolower((string) preg_replace('/\b(md|mr|mrs|ms|mst|engr|dr)\b\.?/i', '', (string) $name)));

        return strlen($key) >= 6 ? $key : null;
    }

    /**
     * PM = the employee with most of the project's tasks; every assignee and support officer joins
     * the team with a role from their department (spec L14).
     *
     * @param  Collection<int, stdClass>  $tasks
     * @return array{manager: int|null, team: array<int, array{0: string, 1: string|null}>}
     */
    private function people(LegacyContext $context, Collection $tasks): array
    {
        $assignees = $tasks->map(fn (object $task): ?int => $context->employees[(int) $task->assign_to] ?? null)->filter();
        $manager = $assignees->countBy()->sortDesc()->keys()->first();
        $team = [];

        foreach ($tasks as $task) {
            $since = LegacyMap::date($task->entry_date);

            foreach ([$task->assign_to, $task->support_id] as $legacyEmployeeId) {
                $employeeId = $context->employees[(int) $legacyEmployeeId] ?? null;

                if ($employeeId === null || isset($team[$employeeId])) {
                    continue;
                }

                $role = match (true) {
                    $employeeId === $manager => ProjectRole::PM,
                    ($this->departments[$employeeId] ?? null) === 'DESIGN' => 'ARCHITECT',
                    ($this->departments[$employeeId] ?? null) === 'PROJECT_OPS' => 'SITE_ENGINEER',
                    default => ProjectRole::SUPPORT_OFFICER,
                };

                $team[$employeeId] = [$role, $since];
            }
        }

        return ['manager' => $manager !== null ? (int) $manager : null, 'team' => $team];
    }

    /**
     * The linked lead's services at rate 0; v1 holds no prices (spec L14).
     */
    private function copyServices(LegacyContext $context, Project $project, int $leadId): void
    {
        $statusId = $context->idFor(ProjectServiceStatus::class, ProjectServiceStatus::NOT_STARTED);

        foreach (LeadServiceLine::query()->where('lead_id', $leadId)->orderBy('id')->get() as $index => $line) {
            $project->services()->create([
                'service_id' => $line->service_id,
                'quantity' => 1,
                'rate' => 0,
                'discount_amount' => 0,
                'amount' => 0,
                'project_service_status_id' => $statusId,
                'sort_order' => $index + 1,
            ]);
        }
    }

    /**
     * Each business line's project sequence continues after the highest imported number (MG-AC-06).
     */
    private function advanceSequences(): void
    {
        $definition = NumberSequenceFormat::query()->where('document_type', 'project')->first();

        if ($definition === null) {
            return;
        }

        $numbers = Project::withTrashed()->pluck('project_number');

        foreach ($this->lines as $line) {
            $prefix = (string) $line->project_prefix;
            $max = $numbers->map(fn (string $number): int => preg_match('/^'.preg_quote($prefix, '/').'-(\d+)$/', $number, $match) === 1 ? (int) $match[1] : 0)->max() ?? 0;

            if ($prefix === '' || $max === 0) {
                continue;
            }

            $sequence = NumberSequence::query()->firstOrNew(['document_type' => 'project', 'scope_key' => 'bl:'.$prefix]);
            $sequence->forceFill([
                'format' => $sequence->format ?? $definition->format,
                'reset_policy' => $sequence->reset_policy ?? $definition->reset_policy,
                'next_number' => max((int) $sequence->next_number, $max + 1),
            ])->save();
        }
    }
}
