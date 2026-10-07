<?php

use App\Modules\Catalog\Models\Material;
use App\Modules\Catalog\Models\WorkItem;
use App\Modules\Estimation\Actions\SaveEstimate;
use App\Modules\Estimation\Models\CostCategory;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateKind;
use App\Modules\Estimation\Models\EstimateStatus;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    seedEstimation();
    seedAccessControl();
    $this->pm = staffUser('project_manager');
    $this->project = Project::factory()->managedBy(Employee::query()->findOrFail($this->pm->employee_id))
        ->create(['site_address' => 'Plot 21, Road 5, Aftabnagar']);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function estimateInput(array $overrides = []): array
{
    return [
        'estimate_kind_id' => EstimateKind::idFor(EstimateKind::BOQ),
        'title' => 'Ground floor works',
        'estimate_date' => today()->toDateString(),
        'overhead_pct' => '10',
        'sections' => [
            ['name' => 'Substructure', 'lines' => [
                ['description' => 'Brick work in foundation', 'measurement_formula' => 'nos_l_w_h', 'nos' => '2', 'length' => '20', 'width' => '0.833', 'height' => '10', 'unit_id' => unitId('cft'), 'rate' => '150'],
                ['description' => 'Door opening', 'measurement_formula' => 'nos_l_w_h', 'nos' => '1', 'length' => '3', 'width' => '0.833', 'height' => '7', 'deduction' => true, 'unit_id' => unitId('cft'), 'rate' => '150'],
            ]],
            ['name' => 'Ground floor', 'lines' => [
                ['line_no' => 'GF-1', 'description' => 'Plaster', 'measurement_formula' => 'manual', 'quantity' => '1,200', 'unit_id' => unitId('sft'), 'rate' => '45'],
            ]],
        ],
        'material_lines' => [
            ['material_name' => '16mm Rebar', 'unit_id' => unitId('ton'), 'estimated_qty' => '2', 'wastage_pct' => '5', 'rate' => '95000'],
        ],
        ...$overrides,
    ];
}

test('a new estimate gets a number, sections, computed lines and totals', function () {
    $estimate = app(SaveEstimate::class)->handle($this->pm, estimateInput(), project: $this->project);

    $lines = $estimate->lines;

    expect($estimate->estimate_number)->toStartWith('EST-')
        ->and($estimate->status->code)->toBe(EstimateStatus::DRAFT)
        ->and($estimate->revision_no)->toBe(0)
        ->and($estimate->prepared_by)->toBe($this->pm->employee_id)
        ->and($estimate->site_address)->toBe('Plot 21, Road 5, Aftabnagar')
        ->and($estimate->sections->pluck('name')->all())->toBe(['Substructure', 'Ground floor'])
        ->and($lines->pluck('line_no')->all())->toBe(['1.01', '1.02', 'GF-1'])
        ->and($lines[0]->quantity)->toBe('333.2000')
        ->and($lines[0]->amount)->toBe('49980.00')
        ->and($lines[1]->quantity)->toBe('17.4930')
        ->and($lines[1]->amount)->toBe('-2623.95')
        ->and($lines[2]->quantity)->toBe('1200.0000')
        ->and($lines[2]->quantity_is_manual)->toBeTrue()
        ->and($estimate->materialLines->first()->total_qty)->toBe('2.1000')
        ->and($estimate->subtotal)->toBe('101356.05')
        ->and($estimate->overhead_amount)->toBe('10135.61')
        ->and($estimate->total_amount)->toBe('111491.66')
        ->and($estimate->statusHistories)->toHaveCount(1);
});

test('saving again keeps posted line ids and removes missing lines', function () {
    $estimate = app(SaveEstimate::class)->handle($this->pm, estimateInput(), project: $this->project);
    [$first, , $third] = $estimate->lines->all();

    $input = estimateInput(['sections' => [
        ['id' => $estimate->sections[0]->id, 'name' => 'Substructure', 'lines' => [
            ['id' => $first->id, 'description' => 'Brick work in foundation', 'measurement_formula' => 'nos_l_w_h', 'nos' => '2', 'length' => '20', 'width' => '0.833', 'height' => '10', 'unit_id' => unitId('cft'), 'rate' => '160'],
        ]],
        ['name' => '', 'lines' => [
            ['id' => $third->id, 'description' => 'Plaster', 'measurement_formula' => 'manual', 'quantity' => '1000', 'unit_id' => unitId('sft'), 'rate' => '45'],
        ]],
    ]]);

    $estimate = app(SaveEstimate::class)->handle($this->pm, $input, $estimate);

    expect($estimate->lines->pluck('id')->all())->toBe([$first->id, $third->id])
        ->and($estimate->lines[0]->amount)->toBe('53312.00')
        ->and($estimate->lines[1]->estimate_section_id)->toBeNull()
        ->and($estimate->sections->pluck('name')->all())->toBe(['Substructure']);
});

test('a work item fills nothing by itself but its id is kept and checked', function () {
    $item = WorkItem::factory()->create(['unit_id' => unitId('cft')]);
    $input = estimateInput(['sections' => [['name' => 'Works', 'lines' => [
        ['work_item_id' => $item->id, 'description' => $item->name, 'measurement_formula' => 'nos', 'nos' => '4', 'unit_id' => unitId('nos'), 'rate' => '100', 'cost_category_id' => CostCategory::idFor(CostCategory::LABOUR)],
    ]]]]);

    $estimate = app(SaveEstimate::class)->handle($this->pm, $input, project: $this->project);

    expect($estimate->lines->first())->work_item_id->toBe($item->id)->quantity->toBe('4.0000')->cost_category_id->toBe(CostCategory::idFor(CostCategory::LABOUR));

    $item->update(['is_active' => false]);

    expect(fn () => app(SaveEstimate::class)->handle($this->pm, $input, project: $this->project))->toThrow(ValidationException::class);
});

test('lines are validated', function (array $line, string $field) {
    $input = estimateInput(['sections' => [['name' => 'Works', 'lines' => [[
        'description' => 'Line', 'measurement_formula' => 'nos_l_w', 'nos' => '1', 'length' => '2', 'width' => '3', 'unit_id' => unitId('sft'), ...$line,
    ]]]]]);

    try {
        app(SaveEstimate::class)->handle($this->pm, $input, project: $this->project);
        $this->fail('Expected a validation error.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($field);
    }
})->with([
    'no description' => [['description' => ''], 'sections.0.lines.0.description'],
    'no unit' => [['unit_id' => null], 'sections.0.lines.0.unit_id'],
    'missing width' => [['width' => ''], 'sections.0.lines.0.width'],
    'manual without quantity' => [['measurement_formula' => 'manual'], 'sections.0.lines.0.quantity'],
    'negative quantity' => [['measurement_formula' => 'manual', 'quantity' => '-5'], 'sections.0.lines.0.quantity'],
    'zero nos' => [['nos' => '0'], 'sections.0.lines.0.nos'],
    'foreign line id' => [['id' => 999999], 'sections.0.lines.0.id'],
]);

test('a material line needs a material or a typed name', function () {
    $cement = Material::factory()->create();
    $input = estimateInput(['sections' => [], 'estimate_kind_id' => EstimateKind::idFor(EstimateKind::MATERIAL), 'material_lines' => [
        ['material_id' => $cement->id, 'material_name' => 'ignored', 'unit_id' => unitId('bag'), 'estimated_qty' => '350'],
        ['unit_id' => unitId('bag'), 'estimated_qty' => '10'],
    ]]);

    expect(fn () => app(SaveEstimate::class)->handle($this->pm, $input, project: $this->project))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('material_lines.1.material_id'));

    array_pop($input['material_lines']);
    $estimate = app(SaveEstimate::class)->handle($this->pm, $input, project: $this->project);

    expect($estimate->materialLines->first())->material_id->toBe($cement->id)->material_name->toBeNull();
});

test('a material estimate refuses work lines', function () {
    $input = estimateInput(['estimate_kind_id' => EstimateKind::idFor(EstimateKind::MATERIAL)]);

    expect(fn () => app(SaveEstimate::class)->handle($this->pm, $input, project: $this->project))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('sections'));
});

test('locked estimates cannot be saved (ES-BR-01)', function (string $status) {
    $estimate = Estimate::factory()->onProject($this->project)->withStatus($status)->create();

    expect(fn () => app(SaveEstimate::class)->handle($this->pm, estimateInput(), $estimate))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('estimate'));
})->with([EstimateStatus::SUBMITTED, EstimateStatus::APPROVED, EstimateStatus::SUPERSEDED]);

test('saving a rejected estimate moves it back to draft', function () {
    $estimate = Estimate::factory()->onProject($this->project)->withStatus(EstimateStatus::REJECTED)->create(['prepared_by' => $this->pm->employee_id]);

    $estimate = app(SaveEstimate::class)->handle($this->pm, estimateInput(), $estimate);

    expect($estimate->status->code)->toBe(EstimateStatus::DRAFT)
        ->and($estimate->statusHistories()->first()->note)->toBe('Edited after rejection.');
});

test('an engineer cannot create an estimate on a project they are not on', function () {
    $engineer = staffUser('engineer');

    expect(fn () => app(SaveEstimate::class)->handle($engineer, estimateInput(), project: $this->project))->toThrow(AuthorizationException::class);
});

test('lines of another estimate can be copied into new lines', function () {
    $source = app(SaveEstimate::class)->handle($this->pm, estimateInput(), project: $this->project);
    $copied = SaveEstimate::linesOf($source);

    $copy = app(SaveEstimate::class)->handle($this->pm, estimateInput([...$copied, 'title' => 'Copy']), project: $this->project);

    expect($copy->lines->pluck('description')->all())->toBe($source->lines->pluck('description')->all())
        ->and($copy->lines->pluck('id')->intersect($source->lines->pluck('id'))->all())->toBe([])
        ->and($copy->total_amount)->toBe($source->total_amount)
        ->and($copy->materialLines)->toHaveCount(1);
});
