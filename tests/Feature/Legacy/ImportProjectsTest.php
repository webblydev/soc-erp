<?php

use App\Modules\Crm\Models\Customer;
use App\Modules\Crm\Models\Lead;
use App\Modules\Hrm\Models\Department;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\Task;
use App\Modules\Projects\Models\TaskStatus;
use App\Support\NumberSequenceService;
use Database\Seeders\Catalog\CatalogSeeder;
use Database\Seeders\Foundation\LocationSeeder;
use Database\Seeders\Legacy\ImportClients;
use Database\Seeders\Legacy\ImportProjects;
use Database\Seeders\Legacy\ImportTasks;
use Database\Seeders\Legacy\ImportUsers;
use Database\Seeders\Legacy\LegacyContext;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    useLegacyDatabase();
    seedProjects();
    seedAccessControl();
    $this->seed([CatalogSeeder::class, LocationSeeder::class]);
    $this->admin = superAdmin(['username' => 'admin']);

    legacyRow('tbl_user', ['id' => 4, 'name' => 'Delower', 'user_name' => 'delower', 'type' => 'u', 'status' => 'a']);
    $this->architect = Employee::factory()->inDepartment(Department::query()->where('code', 'DESIGN')->firstOrFail())->create();
    $this->engineer = Employee::factory()->inDepartment(Department::query()->where('code', 'PROJECT_OPS')->firstOrFail())->create();

    $this->project = fn (array $row) => legacyRow('tbl_project', ['project_type_id' => 1, 'name' => 'Project '.$row['id'], 'status' => 'a', 'add_by' => '4', 'add_time' => '2024-01-10 10:00:00', ...$row]);
    $this->task = fn (array $row) => legacyRow('tbl_task', ['entry_date' => '2024-02-01', 'deadline' => '2024-02-10', 'type_id' => 1, 'status' => 'p', 'task_detail' => 'Task '.$row['id'], ...$row]);
});

function importLegacyProjects(LegacyContext $context): LegacyContext
{
    $context->employees = [7 => test()->architect->id, 8 => test()->engineer->id];
    app(ImportUsers::class)->run($context);
    app(ImportClients::class)->run($context);
    app(ImportProjects::class)->run($context);
    app(ImportTasks::class)->run($context);

    return $context;
}

test('projects keep their number, type and business line; deleted and test rows are skipped', function () {
    ($this->project)(['id' => 5, 'project_id' => "\tSOC-BD&RA-0001 ", 'project_type_id' => 2]);
    ($this->project)(['id' => 6, 'project_id' => 'SOC-CON-UPS-0003', 'project_type_id' => 16]);
    ($this->project)(['id' => 7, 'project_id' => 'SOC-MANAGEMENT-001', 'project_type_id' => 10, 'name' => 'HR & ADMIN']);
    ($this->project)(['id' => 8, 'project_id' => 'Test_id', 'status' => 'd']);
    ($this->project)(['id' => 9, 'project_id' => '01111', 'project_type_id' => 6]);

    importLegacyProjects(new LegacyContext);

    $projects = Project::query()->with(['businessLine', 'type'])->orderBy('legacy_project_ref')->get();
    expect($projects->pluck('project_number')->all())->toBe(['SOC-BD&RA-0001', 'SOC-CON-UPS-0003', 'SOC-MANAGEMENT-001'])
        ->and($projects->map(fn ($p) => [$p->businessLine->code, $p->type->code])->all())->toBe([['BDRA', 'RESIDENTIAL'], ['CON-UPS', 'ESTIMATE'], ['MGT', 'INTERNAL']])
        ->and($projects[2]->customer_id)->toBeNull()
        ->and($projects[0]->status->code)->toBe('IN_PROGRESS')
        ->and($projects[0]->created_by)->not->toBeNull();
});

test('a reused v1 number stays on the earliest row; the later one gets its id appended', function () {
    ($this->project)(['id' => 195, 'project_id' => 'SOC-BD-0107', 'name' => 'Sunrise Tower']);
    ($this->project)(['id' => 196, 'project_id' => 'SOC-BD-0107', 'name' => 'Khilbarirtek']);

    importLegacyProjects(new LegacyContext);

    expect(Project::query()->orderBy('legacy_project_ref')->pluck('project_number')->all())->toBe(['SOC-BD-0107', 'SOC-BD-0107-196'])
        ->and(Project::query()->where('legacy_project_ref', 196)->value('notes'))->toContain('SOC-BD-0107');
});

test('the customer comes from the linked client, the tasks, a name match or a new customer', function () {
    legacyRow('tbl_client', ['id' => 1, 'client_id' => 'SOC-CON-00001', 'client_name' => 'Linked Client', 'phone' => '01711000001', 'status' => 's', 'project_id' => 10, 'requirement' => '1', 'add_by' => 4, 'add_time' => '2024-01-01 10:00:00']);
    legacyRow('tbl_client', ['id' => 2, 'client_id' => 'SOC-CON-00002', 'client_name' => 'Task Client', 'phone' => '01711000002', 'status' => 's', 'add_by' => 4, 'add_time' => '2024-01-01 10:00:00']);
    legacyRow('tbl_client', ['id' => 3, 'client_id' => 'SOC-CON-00003', 'client_name' => 'Mr. Nur Hossain Howlader', 'phone' => '01711000003', 'status' => 's', 'add_by' => 4, 'add_time' => '2024-01-01 10:00:00']);
    ($this->project)(['id' => 10, 'project_id' => 'SOC-BD-0001']);
    ($this->project)(['id' => 11, 'project_id' => 'SOC-BD-0002']);
    ($this->project)(['id' => 12, 'project_id' => 'SOC-BD-0003', 'name' => 'NUR HOSSAIN HOWLADER , SATARKUL']);
    ($this->project)(['id' => 13, 'project_id' => 'SOC-BD-0004', 'name' => 'Unknown Owner']);
    ($this->task)(['id' => 1, 'projectId' => 11, 'client_id' => 'SOC-CON-00002']);
    ($this->task)(['id' => 2, 'projectId' => 13, 'client_name' => 'Walk-in Owner', 'client_phone' => '01811000013']);

    importLegacyProjects(new LegacyContext);

    $customerOf = fn (int $ref) => Project::query()->where('legacy_project_ref', $ref)->firstOrFail()->customer;
    $lead = Lead::query()->where('legacy_client_ref', 1)->firstOrFail();

    expect($customerOf(10)->name)->toBe('Linked Client')
        ->and($lead->converted_project_id)->toBe(Project::query()->where('legacy_project_ref', 10)->value('id'))
        ->and(Project::query()->where('legacy_project_ref', 10)->firstOrFail()->services()->count())->toBe(1)
        ->and($customerOf(11)->name)->toBe('Task Client')
        ->and($customerOf(12)->name)->toBe('Mr. Nur Hossain Howlader')
        ->and(Project::query()->where('legacy_project_ref', 12)->value('notes'))->toContain('matched by name')
        ->and($customerOf(13))->name->toBe('Walk-in Owner')->phone->toBe('01811000013')
        ->and(Customer::query()->count())->toBe(4);
});

test('the PM holds most tasks and the other people join the team', function () {
    ($this->project)(['id' => 20, 'project_id' => 'SOC-BD-0010']);
    ($this->task)(['id' => 1, 'projectId' => 20, 'assign_to' => 8, 'support_id' => 7, 'entry_date' => '2024-03-01']);
    ($this->task)(['id' => 2, 'projectId' => 20, 'assign_to' => 8]);
    ($this->task)(['id' => 3, 'projectId' => 20, 'assign_to' => 7]);

    importLegacyProjects(new LegacyContext);

    $project = Project::query()->where('legacy_project_ref', 20)->firstOrFail();
    expect($project->project_manager_id)->toBe($this->engineer->id)
        ->and($project->start_date->toDateString())->toBe('2024-02-01')
        ->and($project->activeTeam()->with('role')->get()->mapWithKeys(fn ($m) => [$m->employee_id => $m->role->code])->all())
        ->toEqual([$this->engineer->id => 'PM', $this->architect->id => 'ARCHITECT']);
});

test('the project sequence continues after the highest imported number (MG-AC-06)', function () {
    ($this->project)(['id' => 30, 'project_id' => 'SOC-BD-0168']);
    importLegacyProjects(new LegacyContext);

    expect(DB::transaction(fn () => app(NumberSequenceService::class)->next('project', ['bl_prefix' => 'SOC-BD'])))->toBe('SOC-BD-0169');
});

test('tasks map their status, project, people, priority and comments', function () {
    legacyRow('tbl_type', ['id' => 5, 'name' => 'Engineering Service work']);
    ($this->project)(['id' => 40, 'project_id' => 'SOC-BD-0020']);
    ($this->task)(['id' => 1, 'projectId' => 40, 'status' => 'c', 'completed_date' => '2024-02-05', 'assign_to' => 7, 'support_id' => 8, 'is_important' => 'true', 'file_number' => '20230001', 'task_detail' => "Typical floor drawing\nWith notes", 'completed_by_comment' => 'Sent to client']);
    ($this->task)(['id' => 2, 'status' => 'o', 'task_detail' => '', 'type_id' => 5, 'project_name' => 'Head Office']);
    ($this->task)(['id' => 3, 'status' => 'd', 'deadline' => '2024-01-20']);
    ($this->task)(['id' => 4, 'status' => 'p', 'deadline' => '0000-00-00']);

    importLegacyProjects(new LegacyContext);

    $tasks = Task::query()->with(['status', 'priority', 'type'])->orderBy('legacy_task_ref')->get();
    expect($tasks->map(fn ($t) => $t->status->code)->all())->toBe([TaskStatus::DONE, TaskStatus::IN_PROGRESS, TaskStatus::CANCELLED, TaskStatus::TODO])
        ->and($tasks[0])->task_number->toBe('T-24-00001')->title->toBe('Typical floor drawing')->file_number->toBe('20230001')
        ->assignee_employee_id->toBe($this->architect->id)->support_officer_id->toBe($this->engineer->id)->is_important->toBeTrue()
        ->and($tasks[0]->project_id)->toBe(Project::query()->where('legacy_project_ref', 40)->value('id'))
        ->and($tasks[0]->priority->code)->toBe('HIGH')
        ->and($tasks[0]->completed_at->toDateString())->toBe('2024-02-05')
        ->and($tasks[0]->comments()->value('body'))->toBe('Sent to client')
        ->and($tasks[1])->project_id->toBeNull()->title->toBe('Engineering Service work — Head Office')
        ->and($tasks[1]->type->code)->toBe('STRUCTURAL_CALC')
        ->and($tasks[2]->archived_at)->not->toBeNull()
        ->and($tasks[2]->due_date->toDateString())->toBe('2024-02-01')
        ->and($tasks[3]->due_date)->toBeNull();
});

test('running again adds nothing', function () {
    ($this->project)(['id' => 50, 'project_id' => 'SOC-BD-0030']);
    ($this->task)(['id' => 1, 'projectId' => 50]);

    importLegacyProjects(new LegacyContext);
    $context = importLegacyProjects(new LegacyContext);

    expect(Project::query()->count())->toBe(1)->and(Task::query()->count())->toBe(1)
        ->and($context->projects)->toBe([50 => Project::query()->value('id')]);
});
