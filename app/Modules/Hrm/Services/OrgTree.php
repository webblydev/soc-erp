<?php

namespace App\Modules\Hrm\Services;

use App\Modules\Hrm\Models\Employee;
use Illuminate\Support\Collection;

/**
 * The reporting tree of employees in active employment (docs/09 §4.7). Roots are employees
 * without a manager in active employment. Anyone caught in a manager loop in old data becomes a
 * root instead of disappearing.
 *
 * @phpstan-type OrgNode array{employee: Employee, children: list<array<string, mixed>>}
 */
final class OrgTree
{
    /**
     * @return list<OrgNode>
     */
    public function build(?int $departmentId = null): array
    {
        /** @var Collection<int, Employee> $employees */
        $employees = Employee::query()->assignable()
            ->with('designation:id,name')
            ->orderBy('full_name')
            ->get(['id', 'employee_code', 'full_name', 'photo_path', 'designation_id', 'department_id', 'manager_id'])
            ->keyBy('id');

        $kept = $departmentId === null ? $employees->keys()->all() : $this->withAncestors($employees, $departmentId);
        $keptSet = array_flip($kept);

        $children = [];
        $roots = [];

        foreach ($employees as $employee) {
            if (! isset($keptSet[$employee->id])) {
                continue;
            }

            if ($employee->manager_id !== null && isset($keptSet[$employee->manager_id])) {
                $children[$employee->manager_id][] = $employee;
            } else {
                $roots[] = $employee;
            }
        }

        $visited = [];
        $tree = [];

        foreach ($roots as $root) {
            $tree[] = $this->node($root, $children, $visited);
        }

        foreach ($employees as $employee) {
            if (isset($keptSet[$employee->id]) && ! isset($visited[$employee->id])) {
                $tree[] = $this->node($employee, $children, $visited);
            }
        }

        return $tree;
    }

    /**
     * Members of the department plus everyone above them.
     *
     * @param  Collection<int, Employee>  $employees
     * @return list<int>
     */
    private function withAncestors(Collection $employees, int $departmentId): array
    {
        $kept = [];

        foreach ($employees as $employee) {
            if ($employee->department_id !== $departmentId) {
                continue;
            }

            for ($current = $employee; $current !== null && ! isset($kept[$current->id]); $current = $employees->get((int) $current->manager_id)) {
                $kept[$current->id] = true;
            }
        }

        return array_keys($kept);
    }

    /**
     * @param  array<int, list<Employee>>  $children
     * @param  array<int, true>  $visited
     * @return OrgNode
     */
    private function node(Employee $employee, array $children, array &$visited): array
    {
        $visited[$employee->id] = true;
        $nodes = [];

        foreach ($children[$employee->id] ?? [] as $child) {
            if (! isset($visited[$child->id])) {
                $nodes[] = $this->node($child, $children, $visited);
            }
        }

        return ['employee' => $employee, 'children' => $nodes];
    }
}
