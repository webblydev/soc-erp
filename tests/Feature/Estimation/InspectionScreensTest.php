<?php

use App\Modules\Estimation\Livewire\Inspections\Form;
use App\Modules\Estimation\Livewire\Inspections\Index;
use App\Modules\Estimation\Livewire\Inspections\Show;
use App\Modules\Estimation\Models\FindingSeverity;
use App\Modules\Estimation\Models\FindingStatus;
use App\Modules\Estimation\Models\InspectionStatus;
use App\Modules\Estimation\Models\SiteInspection;
use App\Modules\Estimation\Models\SiteInspectionFinding;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectEmployee;
use Database\Seeders\Foundation\DocumentTypeSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    seedEstimation();
    seedAccessControl();
    $this->seed(DocumentTypeSeeder::class);
    Notification::fake();
    Storage::fake(config('foundation.attachments.disk'));
    $this->pm = staffUser('project_manager');
    $this->engineer = staffUser('engineer');
    $this->project = Project::factory()->managedBy(Employee::query()->findOrFail($this->pm->employee_id))->create(['project_number' => 'SOC-BD&RA-0047', 'name' => 'Mojibur Rahman & Gong P-01']);
    ProjectEmployee::factory()->create(['project_id' => $this->project->id, 'employee_id' => $this->engineer->employee_id]);
});

test('the list shows visible inspections with their open findings', function () {
    $inspection = SiteInspection::factory()->onProject($this->project)->withStatus(InspectionStatus::SUBMITTED)->create(['contractor_name' => 'Shah Alam']);
    SiteInspectionFinding::factory()->forInspection($inspection)->count(2)->create();
    SiteInspection::factory()->create(['contractor_name' => 'Someone else']);

    $this->actingAs(staffUser('sales_executive'))->get(route('site.inspections.index'))->assertForbidden();

    Livewire::actingAs($this->engineer)->test(Index::class)
        ->assertSee('Shah Alam')->assertDontSee('Someone else')->assertSeeHtml('>2</span> / 2')
        ->set('filters.open', '1')->assertSee('Shah Alam')
        ->call('export')->assertFileDownloaded();
});

test('an inspection with three findings and photos is filed from a phone and prints the v1 fields (ES-AC-06)', function () {
    Livewire::actingAs($this->engineer)->withQueryParams(['project' => 'SOC-BD&RA-0047'])->test(Form::class)
        ->set('start_time', '08:30')
        ->set('end_time', '18:14')
        ->set('contractor_name', 'Shah Alam')
        ->set('permittee_name', 'Md. Atiar Rahman')
        ->set('field_office_phone', '01794544173')
        ->set('work_progress_summary', 'Twelve columns cast')
        ->call('addFinding')->set('findings.0.location', '1st floor roof')->set('findings.0.description', 'Main rod short in slab')
        ->call('addFinding')->set('findings.1.location', 'Stair')->set('findings.1.description', 'Lapping position wrong')
        ->set('findings.1.finding_severity_id', FindingSeverity::idFor(FindingSeverity::HIGH))
        ->set('findings.1.responsible_type', 'employee')->set('findings.1.responsible_id', $this->engineer->employee_id)
        ->set('findings.1.due_date', today()->addWeek()->toDateString())
        ->call('addFinding')->set('findings.2.location', 'Kitchen wall')->set('findings.2.description', 'Power board near door frame')
        ->call('saveAndSubmit')
        ->assertHasNoErrors()
        ->assertRedirect();

    $inspection = SiteInspection::query()->sole();

    expect($inspection->status->code)->toBe(InspectionStatus::SUBMITTED)->and($inspection->findings)->toHaveCount(3);

    $show = Livewire::actingAs($this->engineer)->test(Show::class, ['inspection' => $inspection]);

    foreach ($inspection->findings as $finding) {
        $show->call('startPhoto', $finding->id)->set('photo', UploadedFile::fake()->image('site.jpg'))->call('uploadPhoto')->assertHasNoErrors();
    }

    $this->actingAs($this->engineer)->get(route('site.inspections.print', $inspection))
        ->assertOk()
        ->assertSee('Site inspection report')
        ->assertSee('Construction ID')->assertSee('SOC-BD&amp;RA-0047', false)
        ->assertSee('Constructor')->assertSee('Shah Alam')
        ->assertSee('Permittee')->assertSee('Md. Atiar Rahman')
        ->assertSee('Field office phone')->assertSee('01794544173')
        ->assertSee('08:30 – 18:14')
        ->assertSee('Main rod short in slab')->assertSee('Power board near door frame')
        ->assertSee('Photos');
});

test('high findings without a responsible stay on the form with errors', function () {
    Livewire::actingAs($this->engineer)->withQueryParams(['project' => 'SOC-BD&RA-0047'])->test(Form::class)
        ->call('addFinding')
        ->set('findings.0.description', 'Crack in beam')
        ->set('findings.0.finding_severity_id', FindingSeverity::idFor(FindingSeverity::CRITICAL))
        ->call('save')
        ->assertHasErrors(['findings.0.responsible_type', 'findings.0.due_date']);
});

test('the detail page adds a finding, changes a status with an after photo and closes', function () {
    $inspection = SiteInspection::factory()->onProject($this->project)->withStatus(InspectionStatus::SUBMITTED)->create();
    $finding = SiteInspectionFinding::factory()->forInspection($inspection)->create();

    $component = Livewire::actingAs($this->pm)->test(Show::class, ['inspection' => $inspection])
        ->set('newFinding.description', 'Column cover missing')
        ->call('addFinding')
        ->assertHasNoErrors()
        ->assertSee('Column cover missing')
        ->call('openStatus', $finding->id)
        ->set('statusCode', FindingStatus::RESOLVED)
        ->call('changeStatus')
        ->assertHasErrors('statusNote')
        ->set('statusNote', 'Fixed on site')
        ->set('statusPhoto', UploadedFile::fake()->image('after.jpg'))
        ->call('changeStatus')
        ->assertHasNoErrors();

    expect($finding->fresh()->status->code)->toBe(FindingStatus::RESOLVED)
        ->and($finding->attachments()->first()->title)->toStartWith('After:');

    $added = $inspection->findings()->where('description', 'Column cover missing')->first();
    $component->call('openStatus', $added->id)->set('statusCode', FindingStatus::ACCEPTED)->set('statusNote', 'Client accepts')->call('changeStatus');

    expect($inspection->fresh()->status->code)->toBe(InspectionStatus::CLOSED);
});

test('another engineer cannot move a finding that is not theirs', function () {
    $inspection = SiteInspection::factory()->onProject($this->project)->withStatus(InspectionStatus::SUBMITTED)->create();
    $finding = SiteInspectionFinding::factory()->forInspection($inspection)->create();

    Livewire::actingAs($this->engineer)->test(Show::class, ['inspection' => $inspection])
        ->assertDontSee('Change status')
        ->call('openStatus', $finding->id)->set('statusCode', FindingStatus::IN_PROGRESS)->call('changeStatus')
        ->assertHasErrors('statusNote');
});
