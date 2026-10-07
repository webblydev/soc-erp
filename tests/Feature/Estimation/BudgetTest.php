<?php

use App\Modules\Estimation\Actions\ApproveEstimate;
use App\Modules\Estimation\Actions\BuildBudgetFromEstimate;
use App\Modules\Estimation\Actions\ReviseEstimate;
use App\Modules\Estimation\Actions\SaveBudget;
use App\Modules\Estimation\Actions\SubmitEstimate;
use App\Modules\Estimation\Contracts\BudgetCostSource;
use App\Modules\Estimation\Models\CostCategory;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateKind;
use App\Modules\Estimation\Models\EstimateLine;
use App\Modules\Estimation\Models\EstimateStatus;
use App\Modules\Estimation\Services\BudgetCostSources;
use App\Modules\Estimation\Services\BudgetSummary;
use App\Modules\Foundation\Models\Setting;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use App\Support\Facades\Settings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    seedEstimation();
    seedAccessControl();
    Notification::fake();
    $this->pm = staffUser('project_manager');
    $this->project = Project::factory()->managedBy(Employee::query()->findOrFail($this->pm->employee_id))->create();
});

/**
 * A submitted BOQ with labour 30,000, material 50,000, an uncategorised 5,000 and a 2,000 deduction.
 */
function submittedBoq(): Estimate
{
    $estimate = Estimate::factory()->onProject(test()->project)->withStatus(EstimateStatus::SUBMITTED)->create(['prepared_by' => test()->pm->employee_id]);
    $line = fn (string $quantity, ?string $category, bool $deduction = false) => EstimateLine::factory()->quantity($quantity, '100')->create([
        'estimate_id' => $estimate->id, 'cost_category_id' => $category === null ? null : CostCategory::idFor($category),
        'deduction' => $deduction, 'amount' => ($deduction ? '-' : '').bcmul($quantity, '100', 2),
    ]);
    $line('300', CostCategory::LABOUR);
    $line('500', CostCategory::MATERIAL);
    $line('20', CostCategory::MATERIAL, deduction: true);
    $line('50', null);

    return $estimate;
}

test('approving a BOQ builds one budget line per cost category', function () {
    app(ApproveEstimate::class)->handle($this->pm, submittedBoq());

    $lines = $this->project->budgetLines()->with('costCategory')->get()->mapWithKeys(fn ($line) => [$line->costCategory->code => $line->budget_amount]);

    expect($lines->all())->toBe(['LABOUR' => '30000.00', 'MATERIAL' => '48000.00', 'OTHER' => '5000.00'])
        ->and($this->project->fresh()->budget_cost)->toBe('83000.00')
        ->and($this->project->budgetRevisions()->first())
        ->revision_no->toBe(1)->old_total->toBe('0.00')->new_total->toBe('83000.00')->approved_by->toBe($this->pm->id);
});

test('the budget is not built when the setting is off', function () {
    Setting::query()->where('group', 'estimation')->where('key', 'auto_budget_from_approved_estimate')->update(['value' => false]);
    Settings::flush();

    app(ApproveEstimate::class)->handle($this->pm, submittedBoq());

    expect($this->project->budgetLines()->count())->toBe(0);
});

test('approving a revision rebuilds only that estimate family\'s lines (ES-AC-02)', function () {
    $original = submittedBoq();
    app(ApproveEstimate::class)->handle($this->pm, $original);
    app(SaveBudget::class)->handle($this->pm, $this->project, [['cost_category_id' => CostCategory::idFor(CostCategory::APPROVAL_FEE), 'budget_amount' => '15,000']], 'RAJUK fee');

    $revision = app(ReviseEstimate::class)->handle($this->pm, $original->fresh(), 'More labour');
    $revision->lines()->whereHas('costCategory', fn ($query) => $query->where('code', CostCategory::LABOUR))->first()
        ->update(['quantity' => 400, 'amount' => 40000]);
    app(SubmitEstimate::class)->handle($this->pm, $revision);
    app(ApproveEstimate::class)->handle($this->pm, $revision->fresh());

    $lines = $this->project->budgetLines()->get();

    expect($lines)->toHaveCount(4)
        ->and($lines->whereNotNull('source_estimate_id')->pluck('source_estimate_id')->unique()->all())->toBe([$revision->id])
        ->and($this->project->fresh()->budget_cost)->toBe('108000.00')
        ->and($this->project->budgetRevisions()->pluck('revision_no')->all())->toBe([3, 2, 1]);
});

test('a material estimate gives one material line per material', function () {
    $estimate = Estimate::factory()->onProject($this->project)->ofKind(EstimateKind::MATERIAL)->approved()->create();
    $estimate->materialLines()->create(['material_name' => '16mm Rebar', 'unit_id' => unitId('ton'), 'estimated_qty' => 2, 'total_qty' => '2.1', 'rate' => 95000, 'amount' => '199500.00']);

    app(BuildBudgetFromEstimate::class)->handle($estimate, $this->pm);

    expect($this->project->budgetLines()->first())
        ->material_name->toBe('16mm Rebar')->budget_qty->toBe('2.1000')->budget_amount->toBe('199500.00')
        ->and($this->project->budgetLines()->first()->costCategory->code)->toBe(CostCategory::MATERIAL);
});

test('only an approved BOQ or material estimate builds the budget, by a budget manager', function () {
    $draft = Estimate::factory()->onProject($this->project)->create();
    $engineer = staffUser('engineer');

    expect(fn () => app(BuildBudgetFromEstimate::class)->handle($draft, $this->pm))->toThrow(ValidationException::class)
        ->and(fn () => app(BuildBudgetFromEstimate::class)->handle(Estimate::factory()->onProject($this->project)->ofKind(EstimateKind::BLE)->approved()->create(), $this->pm))->toThrow(ValidationException::class)
        ->and(fn () => app(BuildBudgetFromEstimate::class)->handle($draft, $engineer))->toThrow(AuthorizationException::class);
});

test('hand-made lines need a reason once the budget is approved (ES-BR-05)', function () {
    $fee = ['cost_category_id' => CostCategory::idFor(CostCategory::APPROVAL_FEE), 'description' => 'RAJUK fee', 'budget_amount' => '15000'];

    app(SaveBudget::class)->handle($this->pm, $this->project, [$fee]);

    expect($this->project->budgetRevisions()->first())->reason->toBeNull()->new_total->toBe('15000.00');

    $line = $this->project->budgetLines()->first();

    expect(fn () => app(SaveBudget::class)->handle($this->pm, $this->project, [['id' => $line->id, ...$fee, 'budget_amount' => '18000']]))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('reason'));

    app(SaveBudget::class)->handle($this->pm, $this->project, [['id' => $line->id, ...$fee, 'budget_amount' => '18000']], 'Fee went up');

    expect($line->fresh()->budget_amount)->toBe('18000.00')
        ->and($this->project->budgetRevisions()->first())->revision_no->toBe(2)->reason->toBe('Fee went up')->old_total->toBe('15000.00');

    app(SaveBudget::class)->handle($this->pm, $this->project, [['id' => $line->id, ...$fee, 'budget_amount' => '18000', 'description' => 'RAJUK fees']]);

    expect($this->project->budgetRevisions()->count())->toBe(2);
});

test('budget amounts cannot be negative and estimate lines cannot be edited by hand', function () {
    app(ApproveEstimate::class)->handle($this->pm, submittedBoq());
    $built = $this->project->budgetLines()->first();

    expect(fn () => app(SaveBudget::class)->handle($this->pm, $this->project, [['cost_category_id' => CostCategory::idFor(CostCategory::OTHER), 'budget_amount' => '-5']], 'x'))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(SaveBudget::class)->handle($this->pm, $this->project, [['id' => $built->id, 'cost_category_id' => $built->cost_category_id, 'budget_amount' => '1']], 'x'))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('lines.0.id'));
});

test('the summary shows budget, committed, actual, remaining and variance (ES-AC-07)', function () {
    app(SaveBudget::class)->handle($this->pm, $this->project, [['cost_category_id' => CostCategory::idFor(CostCategory::MATERIAL), 'budget_amount' => '20,00,000']]);

    app(BudgetCostSources::class)->register(FakeVendorBillCosts::class);

    $summary = app(BudgetSummary::class)->for($this->project);
    $material = $summary['rows'][0];

    expect($material['category']->code)->toBe(CostCategory::MATERIAL)
        ->and($material)->budget->toBe('2000000.00')->committed->toBe('0.00')->actual->toBe('1250000.00')
        ->remaining->toBe('750000.00')->variance_pct->toBe('-37.50')
        ->and($summary['rows'][1]['category']->code)->toBe(CostCategory::TRANSPORT)
        ->and($summary['rows'][1]['variance_pct'])->toBeNull()
        ->and($summary['totals'])->budget->toBe('2000000.00')->actual->toBe('1290000.00');
});

class FakeVendorBillCosts implements BudgetCostSource
{
    public function committed(Project $project): array
    {
        return [];
    }

    public function actual(Project $project): array
    {
        return [CostCategory::idFor(CostCategory::MATERIAL) => '1250000.00', CostCategory::idFor(CostCategory::TRANSPORT) => '40000'];
    }
}
