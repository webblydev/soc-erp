<?php

use App\Models\User;
use App\Modules\Hrm\Livewire\Employees\Index;
use App\Modules\Hrm\Models\Department;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeStatus;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

beforeEach(fn () => Model::preventLazyLoading());
afterEach(fn () => Model::preventLazyLoading(false));
beforeEach(fn () => seedHrm());

test('the directory needs view_basic', function () {
    $this->actingAs(userWithPermissions('crm.leads.view_own'));
    $this->get(route('hrm.employees.index'))->assertForbidden();

    Employee::factory()->create(['full_name' => 'Shirin Sultana']);
    $this->actingAs(userWithPermissions('hrm.employees.view_basic'));
    $this->get(route('hrm.employees.index'))->assertOk()->assertSee('Shirin Sultana');
});

test('the directory shows active employment by default and filters by department and search', function () {
    $design = Department::query()->where('code', 'DESIGN')->first();
    $designer = Employee::factory()->inDepartment($design)->create(['full_name' => 'Design Person', 'phone' => '01911000111']);
    $ops = Employee::factory()->create(['full_name' => 'Ops Person']);
    $gone = Employee::factory()->withStatus(EmployeeStatus::RESIGNED)->create(['full_name' => 'Gone Person']);

    // The manager filter lists names too, so rows are checked by their wire:key.
    $row = fn (Employee $employee): string => 'wire:key="employee-'.$employee->id.'"';
    $component = Livewire::actingAs(userWithPermissions('hrm.employees.view_basic'))->test(Index::class);

    $component->assertSeeHtml($row($designer))->assertSeeHtml($row($ops))->assertDontSeeHtml($row($gone));
    $component->set('filters.status', 'all')->assertSeeHtml($row($gone));
    $component->set('filters', ['department' => (string) $design->id])->assertSeeHtml($row($designer))->assertDontSeeHtml($row($ops));
    $component->set('filters', [])->set('search', '01911000111')->assertSeeHtml($row($designer))->assertDontSeeHtml($row($ops));
});

test('the card view lists the same employees', function () {
    Employee::factory()->create(['full_name' => 'Card Person']);

    Livewire::actingAs(userWithPermissions('hrm.employees.view_basic'))->test(Index::class)
        ->set('view', 'cards')->assertSee('Card Person');
});

test('export needs the export permission', function () {
    Employee::factory()->create(['nid_number' => '1990999999']);

    Livewire::actingAs(userWithPermissions('hrm.employees.view_basic'))->test(Index::class)->call('export')->assertForbidden();

    Livewire::actingAs(userWithPermissions('hrm.employees.view_basic', 'hrm.employees.export'))->test(Index::class)
        ->call('export')->assertFileDownloaded();
});

test('export columns follow field visibility', function () {
    $basic = Index::exportColumns(userWithPermissions('hrm.employees.view_basic'));
    $full = Index::exportColumns(userWithPermissions('hrm.employees.view_basic', 'hrm.employees.view_full', 'hrm.employees.view_salary'));

    expect($basic)->not->toHaveKey('NID')->not->toHaveKey('Gross salary')
        ->and($full)->toHaveKey('NID')->toHaveKey('Gross salary');
});

test('the HRM nav shows Employees for view_basic', function () {
    $this->actingAs(userWithPermissions('hrm.employees.view_basic'));
    $this->get(route('hrm.employees.index'))->assertSee(route('hrm.employees.index'));
});

test('bulk delete skips employees linked to a login or managing others', function () {
    $linked = Employee::factory()->linkedTo(User::factory()->create())->create();
    $manager = Employee::factory()->create();
    Employee::factory()->create(['manager_id' => $manager->id]);
    $plain = Employee::factory()->create();

    Livewire::actingAs(userWithPermissions('hrm.employees.view_basic'))->test(Index::class)
        ->set('selected', [(string) $plain->id])
        ->call('deleteSelected')
        ->assertForbidden();

    Livewire::actingAs(userWithPermissions('hrm.employees.view_basic', 'hrm.employees.delete'))->test(Index::class)
        ->set('selected', [(string) $linked->id, (string) $manager->id, (string) $plain->id])
        ->call('deleteSelected')
        ->assertDispatched('toast', type: 'warning');

    expect($plain->fresh()->trashed())->toBeTrue()
        ->and($linked->fresh()->trashed())->toBeFalse()
        ->and($manager->fresh()->trashed())->toBeFalse();
});
