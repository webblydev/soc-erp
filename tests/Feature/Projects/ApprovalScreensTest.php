<?php

use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Livewire\Approvals\Form;
use App\Modules\Projects\Livewire\Approvals\Index;
use App\Modules\Projects\Livewire\Approvals\Show;
use App\Modules\Projects\Models\ApprovalAuthority;
use App\Modules\Projects\Models\ApprovalStatus;
use App\Modules\Projects\Models\ApprovalType;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectApproval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    seedProjects();
    seedAccessControl();
    Notification::fake();
    Storage::fake(config('foundation.attachments.disk'));

    $this->engineer = staffUser('engineer');
    $this->project = Project::factory()->managedBy(Employee::factory()->create())->create(['supervisor_id' => $this->engineer->employee_id]);
});

test('the tracker lists pending approvals of visible projects', function () {
    Model::preventLazyLoading();
    $pending = ProjectApproval::factory()->create(['project_id' => $this->project->id, 'reference_no' => 'RAJ-PENDING']);
    ProjectApproval::factory()->withStatus(ApprovalStatus::APPROVED)->create(['project_id' => $this->project->id, 'reference_no' => 'RAJ-DONE']);
    ProjectApproval::factory()->create(['reference_no' => 'RAJ-OTHER']);

    Livewire::actingAs($this->engineer)->test(Index::class)
        ->assertSee('RAJ-PENDING')->assertDontSee('RAJ-DONE')->assertDontSee('RAJ-OTHER')
        ->set('filters.state', 'all')->assertSee('RAJ-DONE');

    Model::preventLazyLoading(false);
});

test('the form creates an approval for a project from the query string', function () {
    Livewire::actingAs($this->engineer)->withQueryParams(['project' => $this->project->project_number])->test(Form::class)
        ->assertSet('project_id', $this->project->id)
        ->set('approval_authority_id', ApprovalAuthority::idFor('RAJUK'))
        ->set('approval_type_id', ApprovalType::idFor('LUC'))
        ->set('submitted_on', '2026-10-01')
        ->call('save')
        ->assertHasNoErrors();

    $approval = ProjectApproval::query()->firstOrFail();
    expect($approval->expected_on->toDateString())->toBe('2026-11-15')->and($approval->checklist()->count())->toBe(6);
});

test('the approval page adds events with a letter', function () {
    $approval = ProjectApproval::factory()->create(['project_id' => $this->project->id]);

    $component = Livewire::actingAs($this->engineer)->test(Show::class, ['approval' => $approval])
        ->set('eventForm.approval_status_id', ApprovalStatus::idFor('APPROVED'))
        ->call('addEvent')
        ->assertHasErrors('file');

    $component->set('file', UploadedFile::fake()->create('approval-letter.pdf', 40, 'application/pdf'))
        ->call('addEvent')
        ->assertHasNoErrors()
        ->assertSee('approval-letter.pdf');

    expect($approval->fresh()->status->code)->toBe(ApprovalStatus::APPROVED);
});

test('the approval page needs the project to be visible', function () {
    $other = ProjectApproval::factory()->create();

    $this->actingAs($this->engineer)->get(route('projects.approvals.show', $other))->assertForbidden();
    $this->actingAs($this->engineer)->get(route('projects.approvals.show', ProjectApproval::factory()->create(['project_id' => $this->project->id])))->assertOk();
});
