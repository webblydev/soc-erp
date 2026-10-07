<?php

use App\Modules\Hrm\Livewire\Employees\Show;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Services\ExitChecks;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectEmployee;
use App\Modules\Projects\Models\Task;
use App\Modules\Projects\Models\TaskStatus;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

beforeEach(function () {
    seedProjects();
    seedAccessControl();

    $this->employee = Employee::factory()->create();
});

test('exit checks list open tasks and each managed project for reassignment (HR-AC-03)', function () {
    $projects = Project::factory()->managedBy($this->employee)->count(3)->create();
    Project::factory()->managedBy($this->employee)->withStatus('COMPLETED')->create();
    Task::factory()->assignedTo($this->employee)->count(2)->create();
    Task::factory()->assignedTo($this->employee)->withStatus(TaskStatus::DONE)->create();

    $labels = collect(app(ExitChecks::class)->run($this->employee))->pluck('label');

    expect($labels)->toContain('2 open tasks to reassign')
        ->and($labels->filter(fn (string $label): bool => str_starts_with($label, 'PM of')))->toHaveCount(3)
        ->and($labels->implode(' '))->toContain($projects->first()->project_number)
        ->and(app(ExitChecks::class)->blocks($this->employee))->toBeFalse();
});

test('the employee profile shows project assignments and open tasks', function () {
    Model::preventLazyLoading();
    $project = Project::factory()->create(['name' => 'Assigned Project']);
    ProjectEmployee::factory()->create(['project_id' => $project->id, 'employee_id' => $this->employee->id]);
    Task::factory()->assignedTo($this->employee)->create(['title' => 'Draw section A']);

    $director = staffUser('management');

    Livewire::actingAs($director)->test(Show::class, ['employee' => $this->employee])
        ->set('tab', 'projects')->assertSee('Assigned Project')
        ->set('tab', 'tasks')->assertSee('Draw section A');

    Model::preventLazyLoading(false);
});
