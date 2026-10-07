<?php

use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeStatus;
use App\Modules\Projects\Livewire\Projects\Index;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectApproval;
use App\Modules\Projects\Models\ProjectStatus;
use App\Modules\Projects\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    seedProjects();
    seedAccessControl();
    Notification::fake();
    Model::preventLazyLoading();
});
afterEach(fn () => Model::preventLazyLoading(false));

$row = fn (Project $project): string => 'wire:key="project-'.$project->id.'"';

test('the list needs a projects view permission and shows only visible projects (PRJ-AC-05)', function () {
    $this->actingAs(userWithPermissions('crm.leads.view_own'));
    $this->get(route('projects.projects.index'))->assertForbidden();

    $engineer = staffUser('engineer');
    $mine = Project::factory()->create(['supervisor_id' => $engineer->employee_id, 'name' => 'Mine Project']);
    $other = Project::factory()->create(['name' => 'Other Project']);

    $this->actingAs($engineer)->get(route('projects.projects.index'))->assertOk()->assertSee('Mine Project')->assertDontSee('Other Project');
});

test('filters, presets and search narrow the list', function () use ($row) {
    $director = staffUser('management');
    $active = Project::factory()->create(['name' => 'Bonosree Residence']);
    $hold = Project::factory()->withStatus(ProjectStatus::ON_HOLD)->create();
    $withOverdue = Project::factory()->create();
    Task::factory()->onProject($withOverdue)->create(['due_date' => today()->subDay()]);
    $withApproval = Project::factory()->create();
    ProjectApproval::factory()->create(['project_id' => $withApproval->id]);

    $component = Livewire::actingAs($director)->test(Index::class);

    $component->call('applyPreset', 'on_hold')->assertSeeHtml($row($hold))->assertDontSeeHtml($row($active));
    $component->call('applyPreset', 'approvals_pending')->assertSeeHtml($row($withApproval))->assertDontSeeHtml($row($hold));
    $component->set('filters', ['overdue' => '1'])->assertSeeHtml($row($withOverdue))->assertDontSeeHtml($row($active));
    $component->set('filters', [])->set('search', 'bonosree')->assertSeeHtml($row($active))->assertDontSeeHtml($row($hold));
});

test('the PM inactive filter finds open projects whose PM left', function () use ($row) {
    $left = Employee::factory()->withStatus(EmployeeStatus::RESIGNED)->create();
    $orphan = Project::factory()->managedBy($left)->create();
    $fine = Project::factory()->managedBy(Employee::factory()->create())->create();

    Livewire::actingAs(staffUser('management'))->test(Index::class)
        ->set('filters', ['pm_inactive' => '1'])
        ->assertSeeHtml($row($orphan))->assertDontSeeHtml($row($fine));
});

test('bulk change PM reassigns the selected projects', function () {
    $project = Project::factory()->create();
    $newPm = Employee::factory()->create();

    Livewire::actingAs(staffUser('management'))->test(Index::class)
        ->set('selected', [(string) $project->id])
        ->set('bulkManagerId', (string) $newPm->id)
        ->call('bulkChangeManager');

    expect($project->fresh()->project_manager_id)->toBe($newPm->id)
        ->and($project->activeTeam()->where('employee_id', $newPm->id)->exists())->toBeTrue();
});

test('export needs the export permission', function () {
    Project::factory()->create();

    Livewire::actingAs(staffUser('engineer'))->test(Index::class)->call('export')->assertForbidden();
    Livewire::actingAs(staffUser('management'))->test(Index::class)->call('export')->assertFileDownloaded();
});
