<?php

use App\Modules\Catalog\Models\WorkItem;
use App\Modules\Estimation\Exports\EstimateLinesExport;
use App\Modules\Estimation\Livewire\Estimates\Editor;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateKind;
use App\Modules\Estimation\Models\EstimateLine;
use App\Modules\Estimation\Models\EstimateStatus;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    seedEstimation();
    seedAccessControl();
    Notification::fake();
    $this->pm = staffUser('project_manager');
    $this->project = Project::factory()->managedBy(Employee::query()->findOrFail($this->pm->employee_id))->create(['project_number' => 'SOC-BD-0103', 'site_address' => 'Satarkul']);
});

test('an estimate is created from the editor with live quantities', function () {
    Livewire::actingAs($this->pm)->withQueryParams(['project' => 'SOC-BD-0103'])->test(Editor::class)
        ->assertSet('project_id', $this->project->id)
        ->assertSet('site_address', 'Satarkul')
        ->set('title', 'Ground floor works')
        ->set('sections.0.name', 'Substructure')
        ->set('sections.0.lines.0.description', 'Brick work')
        ->set('sections.0.lines.0.nos', '2')
        ->set('sections.0.lines.0.length', '20')
        ->set('sections.0.lines.0.width', '0.833')
        ->set('sections.0.lines.0.height', '10')
        ->set('sections.0.lines.0.unit_id', unitId('cft'))
        ->set('sections.0.lines.0.rate', '150')
        ->assertSee('333.2')
        ->assertSee('49,980.00')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $estimate = Estimate::query()->sole();

    expect($estimate)->title->toBe('Ground floor works')->total_amount->toBe('49980.00')
        ->and($estimate->lines->first()->quantity)->toBe('333.2000');
});

test('picking a work item fills the line', function () {
    $item = WorkItem::factory()->create(['name' => 'Plaster 12mm', 'unit_id' => unitId('sft'), 'measurement_formula' => 'nos_l_w', 'standard_rate' => '45']);

    Livewire::actingAs($this->pm)->test(Editor::class)
        ->set('sections.0.lines.0.work_item_id', $item->id)
        ->assertSet('sections.0.lines.0.description', 'Plaster 12mm')
        ->assertSet('sections.0.lines.0.unit_id', unitId('sft'))
        ->assertSet('sections.0.lines.0.measurement_formula', 'nos_l_w')
        ->assertSet('sections.0.lines.0.rate', '45');
});

test('lines can be added, duplicated, moved and removed', function () {
    Livewire::actingAs($this->pm)->test(Editor::class)
        ->set('sections.0.lines.0.description', 'First')
        ->call('addLine', 0)
        ->set('sections.0.lines.1.description', 'Second')
        ->call('duplicateLine', 0, 0)
        ->assertSet('sections.0.lines.1.description', 'First')
        ->call('moveLine', 0, 2, -1)
        ->assertSet('sections.0.lines.1.description', 'Second')
        ->call('removeLine', 0, 0)
        ->assertCount('sections.0.lines', 2)
        ->call('addSection')
        ->assertCount('sections', 2);
});

test('editing keeps line ids and validation errors stay on the form', function () {
    $estimate = Estimate::factory()->onProject($this->project)->withLines(2)->create(['prepared_by' => $this->pm->employee_id]);
    $ids = $estimate->lines->pluck('id')->all();

    Livewire::actingAs($this->pm)->test(Editor::class, ['estimate' => $estimate])
        ->assertSet('sections.0.lines.0.id', $ids[0])
        ->set('sections.0.lines.1.description', '')
        ->call('save')
        ->assertHasErrors('sections.0.lines.1.description')
        ->set('sections.0.lines.1.description', 'Fixed')
        ->call('save')
        ->assertHasNoErrors();

    expect($estimate->fresh()->lines->pluck('id')->all())->toBe($ids);
});

test('a locked estimate redirects to its page', function () {
    $estimate = Estimate::factory()->onProject($this->project)->withStatus(EstimateStatus::SUBMITTED)->create();

    Livewire::actingAs($this->pm)->test(Editor::class, ['estimate' => $estimate])
        ->assertRedirect(route('estimation.estimates.show', $estimate));
});

test('save and submit sends the estimate for approval', function () {
    $estimate = Estimate::factory()->onProject($this->project)->withLines(1)->create(['prepared_by' => $this->pm->employee_id]);

    Livewire::actingAs($this->pm)->test(Editor::class, ['estimate' => $estimate])->call('saveAndSubmit')->assertHasNoErrors();

    expect($estimate->fresh()->status->code)->toBe(EstimateStatus::SUBMITTED);
});

test('lines are copied from another estimate', function () {
    $source = Estimate::factory()->onProject($this->project)->withLines(2)->create();

    Livewire::actingAs($this->pm)->test(Editor::class)
        ->set('copyFrom', 'NOPE')
        ->call('copyLines')
        ->assertHasErrors('copyFrom')
        ->set('copyFrom', $source->estimate_number)
        ->call('copyLines')
        ->assertCount('sections.0.lines', 2)
        ->assertSet('sections.0.lines.0.id', null);
});

test('lines exported to Excel import back, and bad rows are listed', function () {
    $source = Estimate::factory()->onProject($this->project)->withLines(2)->create();
    EstimateLine::factory()->quantity('3')->create(['estimate_id' => $source->id, 'deduction' => true, 'line_no' => '1.03', 'sort_order' => 3]);
    $file = UploadedFile::fake()->createWithContent('lines.xlsx', Excel::raw(new EstimateLinesExport($source), Maatwebsite\Excel\Excel::XLSX));

    $component = Livewire::actingAs($this->pm)->test(Editor::class)
        ->set('importFile', $file)
        ->call('importLines')
        ->assertHasNoErrors()
        ->assertCount('sections.0.lines', 3);

    expect($component->get('sections.0.lines.2.deduction'))->toBeTrue();

    $bad = UploadedFile::fake()->createWithContent('bad.csv', "Section,Line no,Work item code,Description,Unit\n,1,NOPE,Line,sft\n,2,,Line two,parsec\n");

    Livewire::actingAs($this->pm)->test(Editor::class)
        ->set('importFile', $bad)
        ->call('importLines')
        ->assertHasErrors('importFile');
});

test('a material estimate edits material lines', function () {
    Livewire::actingAs($this->pm)->withQueryParams(['project' => 'SOC-BD-0103', 'kind' => EstimateKind::MATERIAL])->test(Editor::class)
        ->set('title', 'Rod estimate')
        ->set('sections', [])
        ->call('addMaterial')
        ->set('materialLines.0.material_name', '16mm Rebar')
        ->set('materialLines.0.unit_id', unitId('ton'))
        ->set('materialLines.0.estimated_qty', '2')
        ->set('materialLines.0.wastage_pct', '5')
        ->set('materialLines.0.rate', '95000')
        ->assertSee('1,99,500.00')
        ->call('save')
        ->assertHasNoErrors();

    expect(Estimate::query()->sole()->total_amount)->toBe('199500.00');
});
