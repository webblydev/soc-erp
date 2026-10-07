<?php

use App\Modules\Estimation\Livewire\Budget\Show;
use App\Modules\Estimation\Models\CostCategory;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectEmployee;
use Livewire\Livewire;

beforeEach(function () {
    seedEstimation();
    seedAccessControl();
    $this->pm = staffUser('project_manager');
    $this->project = Project::factory()->managedBy(Employee::query()->findOrFail($this->pm->employee_id))->create();
});

test('the budget page needs the budget view permission and the project', function () {
    $engineer = staffUser('engineer');

    $this->actingAs($engineer)->get(route('estimation.budget.show', $this->project))->assertForbidden();

    ProjectEmployee::factory()->create(['project_id' => $this->project->id, 'employee_id' => $engineer->employee_id]);

    $this->actingAs($engineer)->get(route('estimation.budget.show', $this->project))->assertOk()->assertSee('No budget yet')->assertDontSee('Edit budget');
});

test('the PM edits the hand-made lines with a reason once approved', function () {
    $component = Livewire::actingAs($this->pm)->test(Show::class, ['project' => $this->project])
        ->call('addLine')
        ->set('lines.0.cost_category_id', CostCategory::idFor(CostCategory::APPROVAL_FEE))
        ->set('lines.0.description', 'RAJUK fee')
        ->set('lines.0.budget_amount', '15,000')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('15,000.00')
        ->assertSee('Rev. 1');

    $component->set('lines.0.budget_amount', '18000')->call('save')->assertHasErrors('reason')
        ->set('reason', 'Fee went up')->call('save')->assertHasNoErrors()->assertSee('Fee went up');

    expect($this->project->fresh()->budget_cost)->toBe('18000.00');
});
