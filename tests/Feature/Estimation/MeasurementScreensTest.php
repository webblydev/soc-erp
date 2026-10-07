<?php

use App\Modules\Estimation\Livewire\Mb\Form;
use App\Modules\Estimation\Livewire\Mb\Index;
use App\Modules\Estimation\Livewire\Mb\Show;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateLine;
use App\Modules\Estimation\Models\MbStatus;
use App\Modules\Estimation\Models\MeasurementEntry;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectEmployee;
use Livewire\Livewire;

beforeEach(function () {
    seedEstimation();
    seedAccessControl();
    $this->pm = staffUser('project_manager');
    $this->engineer = staffUser('engineer');
    $this->project = Project::factory()->managedBy(Employee::query()->findOrFail($this->pm->employee_id))->create(['project_number' => 'SOC-BD-0103']);
    ProjectEmployee::factory()->create(['project_id' => $this->project->id, 'employee_id' => $this->engineer->employee_id]);
    $this->line = EstimateLine::factory()->quantity('100', '50')->create([
        'estimate_id' => Estimate::factory()->onProject($this->project)->approved()->create()->id, 'description' => 'Brick work', 'line_no' => '2.03',
    ]);
});

test('the list needs site.mb.view and shows only visible projects', function () {
    MeasurementEntry::factory()->forLine($this->line)->create(['description' => 'Own entry']);
    MeasurementEntry::factory()->create(['description' => 'Other entry']);

    $this->actingAs(staffUser('sales_executive'))->get(route('site.mb.index'))->assertForbidden();

    Livewire::actingAs($this->engineer)->test(Index::class)->assertSee('Own entry')->assertDontSee('Other entry');
});

test('presets filter the list', function () {
    MeasurementEntry::factory()->forLine($this->line)->create(['description' => 'Waiting one']);
    MeasurementEntry::factory()->forLine($this->line)->withStatus(MbStatus::VERIFIED)->create(['description' => 'Verified one']);

    Livewire::actingAs($this->pm)->test(Index::class)
        ->call('applyPreset', 'awaiting')->assertSee('Waiting one')->assertDontSee('Verified one')
        ->call('applyPreset', 'verified')->assertSee('Verified one')->assertDontSee('Waiting one');
});

test('the PM verifies and rejects selected entries in bulk', function () {
    $first = MeasurementEntry::factory()->forLine($this->line)->create(['measured_by' => $this->engineer->employee_id]);
    $second = MeasurementEntry::factory()->forLine($this->line)->create(['measured_by' => $this->engineer->employee_id]);

    Livewire::actingAs($this->pm)->test(Index::class)
        ->set('selected', [(string) $first->id])
        ->call('verifySelected')
        ->assertDispatched('toast')
        ->set('selected', [(string) $second->id])
        ->set('rejectReason', 'Wrong floor')
        ->call('rejectSelected');

    expect($first->fresh()->status->code)->toBe(MbStatus::VERIFIED)
        ->and($second->fresh()->status->code)->toBe(MbStatus::REJECTED);
});

test('the list exports', function () {
    MeasurementEntry::factory()->forLine($this->line)->create();

    Livewire::actingAs($this->pm)->test(Index::class)->call('export')->assertFileDownloaded();
});

test('the form fills from the BOQ line, shows progress and locks the rate for engineers', function () {
    MeasurementEntry::factory()->forLine($this->line)->create(['quantity' => 90]);

    Livewire::actingAs($this->engineer)->withQueryParams(['project' => 'SOC-BD-0103'])->test(Form::class)
        ->assertSet('project_id', $this->project->id)
        ->set('estimate_line_id', $this->line->id)
        ->assertSet('description', 'Brick work')
        ->assertSet('rate', '50')
        ->set('quantity', '15')
        ->assertSee('105 (105%)')
        ->assertSee('Only a project manager changes the rate.')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(MeasurementEntry::query()->latest('id')->first())->quantity->toBe('15.0000')->achievement_pct->toBe('105.0000');
});

test('a blocked quantity shows the error on the form', function () {
    Livewire::actingAs($this->engineer)->withQueryParams(['project' => 'SOC-BD-0103'])->test(Form::class)
        ->set('estimate_line_id', $this->line->id)
        ->set('quantity', '120')
        ->assertSee('this will be refused')
        ->call('save')
        ->assertHasErrors('quantity');
});

test('the detail page verifies and shows BOQ progress', function () {
    $entry = MeasurementEntry::factory()->forLine($this->line)->create(['quantity' => 40, 'measured_by' => $this->engineer->employee_id]);

    Livewire::actingAs($this->pm)->test(Show::class, ['entry' => $entry])
        ->assertSee('40 / 100')
        ->call('verify')
        ->assertDispatched('toast');

    expect($entry->fresh()->status->code)->toBe(MbStatus::VERIFIED);

    Livewire::actingAs($this->engineer)->test(Show::class, ['entry' => $entry->fresh()])->assertDontSee('Unverify');
});
