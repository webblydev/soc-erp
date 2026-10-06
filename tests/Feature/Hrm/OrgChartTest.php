<?php

use App\Modules\Hrm\Models\Department;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeStatus;
use App\Modules\Hrm\Services\OrgTree;

beforeEach(fn () => seedHrm());

/**
 * @param  list<array{employee: Employee, children: list<mixed>}>  $nodes
 * @return list<string>
 */
function nodeNames(array $nodes): array
{
    return array_map(fn (array $node): string => $node['employee']->full_name, $nodes);
}

test('the tree nests reports under managers and treats exited managers as roots', function () {
    $md = Employee::factory()->create(['full_name' => 'MD']);
    $gm = Employee::factory()->create(['full_name' => 'GM', 'manager_id' => $md->id]);
    Employee::factory()->create(['full_name' => 'Engineer', 'manager_id' => $gm->id]);
    $gone = Employee::factory()->withStatus(EmployeeStatus::RESIGNED)->create(['full_name' => 'Gone']);
    Employee::factory()->create(['full_name' => 'Orphan', 'manager_id' => $gone->id]);

    $tree = app(OrgTree::class)->build();

    expect(nodeNames($tree))->toBe(['MD', 'Orphan'])
        ->and(nodeNames($tree[0]['children']))->toBe(['GM'])
        ->and(nodeNames($tree[0]['children'][0]['children']))->toBe(['Engineer']);
});

test('the department filter keeps members and the chain above them', function () {
    $design = Department::query()->where('code', 'DESIGN')->first();
    $md = Employee::factory()->create(['full_name' => 'MD']);
    Employee::factory()->inDepartment($design)->create(['full_name' => 'Designer', 'manager_id' => $md->id]);
    Employee::factory()->create(['full_name' => 'Accountant', 'manager_id' => $md->id]);

    $tree = app(OrgTree::class)->build($design->id);

    expect(nodeNames($tree))->toBe(['MD'])
        ->and(nodeNames($tree[0]['children']))->toBe(['Designer']);
});

test('a manager loop in old data does not hang the tree', function () {
    $a = Employee::factory()->create(['full_name' => 'A']);
    $b = Employee::factory()->create(['full_name' => 'B', 'manager_id' => $a->id]);
    $a->forceFill(['manager_id' => $b->id])->save();

    $tree = app(OrgTree::class)->build();

    expect(count($tree))->toBeGreaterThanOrEqual(1);
});

test('the org chart page needs view_basic', function () {
    Employee::factory()->create(['full_name' => 'Chart Person']);

    $this->actingAs(userWithPermissions('crm.leads.view_own'))->get(route('hrm.org-chart'))->assertForbidden();
    $this->actingAs(userWithPermissions('hrm.employees.view_basic'))->get(route('hrm.org-chart'))->assertOk()->assertSee('Chart Person');
});
