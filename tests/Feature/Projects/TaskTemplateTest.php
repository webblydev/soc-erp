<?php

use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Actions\AddTeamMember;
use App\Modules\Projects\Actions\ApplyTaskTemplate;
use App\Modules\Projects\Actions\DeleteTaskTemplate;
use App\Modules\Projects\Actions\SaveTaskTemplate;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectRole;
use App\Modules\Projects\Models\Task;
use App\Modules\Projects\Models\TaskTemplate;
use App\Modules\Projects\Models\TaskType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedProjects();
    seedAccessControl();
    Notification::fake();

    $this->pm = staffUser('project_manager');
    $this->project = Project::factory()->managedBy(Employee::query()->findOrFail($this->pm->employee_id))->create(['start_date' => '2026-11-01']);
});

function templateItem(string $title, array $overrides = []): array
{
    return ['title' => $title, 'task_type_id' => TaskType::idFor('DESIGN'), 'offset_days_start' => 0, 'duration_days' => 2, ...$overrides];
}

test('a template saves its items, checklists and dependencies by position', function () {
    $template = app(SaveTaskTemplate::class)->handle($this->pm, ['name' => 'Interior', 'items' => [
        templateItem('Measure', ['checklist' => "Walls\n\nCeiling"]),
        templateItem('Concept', ['offset_days_start' => 2, 'depends_on' => 0]),
    ]]);

    $items = $template->items()->get();
    expect($items->pluck('title')->all())->toBe(['Measure', 'Concept'])
        ->and($items[0]->checklist)->toBe(['Walls', 'Ceiling'])
        ->and($items[1]->depends_on_item_id)->toBe($items[0]->id);
});

test('an item cannot depend on itself or a later item', function () {
    expectValidationError(fn () => app(SaveTaskTemplate::class)->handle($this->pm, ['name' => 'Bad', 'items' => [templateItem('A', ['depends_on' => 0])]]), 'items.0.depends_on');
    expectValidationError(fn () => app(SaveTaskTemplate::class)->handle($this->pm, ['name' => 'Empty', 'items' => []]), 'items');
});

test('editing keeps item ids and removes dropped items', function () {
    $template = app(SaveTaskTemplate::class)->handle($this->pm, ['name' => 'T', 'items' => [templateItem('A'), templateItem('B')]]);
    $first = $template->items()->first();

    app(SaveTaskTemplate::class)->handle($this->pm, ['name' => 'T2', 'items' => [['id' => $first->id, ...templateItem('A renamed')]]], $template);

    expect($template->fresh()->name)->toBe('T2')
        ->and($template->items()->pluck('id')->all())->toBe([$first->id])
        ->and($first->fresh()->title)->toBe('A renamed');
});

test('applying the RAJUK template assigns by team role, else the PM (PRJ-AC-01)', function () {
    $surveyor = Employee::factory()->create();
    app(AddTeamMember::class)->handle($this->pm, $this->project, ['employee_id' => $surveyor->id, 'project_role_id' => ProjectRole::idFor('SURVEYOR'), 'assigned_on' => '2026-11-01']);
    $template = TaskTemplate::query()->firstOrFail();

    $count = app(ApplyTaskTemplate::class)->handle($this->pm, $this->project, $template);

    $tasks = Task::query()->where('project_id', $this->project->id)->orderBy('sort_order')->get();
    expect($count)->toBe(12)
        ->and($tasks[0])->title->toBe('Site survey')->assignee_employee_id->toBe($surveyor->id)
        ->and($tasks[1]->assignee_employee_id)->toBe($this->pm->employee_id)
        ->and($tasks[3]->start_date->toDateString())->toBe('2026-11-14')
        ->and($tasks[3]->due_date->toDateString())->toBe('2026-11-24')
        ->and($tasks[8]->predecessors()->pluck('tasks.id')->all())->toBe([$tasks[7]->id]);
});

test('only template managers save templates, and deleting keeps the tasks', function () {
    expect(fn () => app(SaveTaskTemplate::class)->handle(staffUser('engineer'), ['name' => 'x', 'items' => [templateItem('A')]]))->toThrow(AuthorizationException::class);

    $template = TaskTemplate::query()->firstOrFail();
    app(ApplyTaskTemplate::class)->handle($this->pm, $this->project, $template);
    app(DeleteTaskTemplate::class)->handle($this->pm, $template);

    expect($template->fresh()->trashed())->toBeTrue()->and(Task::query()->count())->toBe(12);
});
