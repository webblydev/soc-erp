<?php

namespace App\Modules\Hrm\Services;

use App\Modules\Hrm\Contracts\EmployeeExitCheck;
use App\Modules\Hrm\Models\Employee;

/**
 * Registry of exit checks (spec H7). Modules register their checks from their service provider.
 */
final class ExitChecks
{
    /** @var list<class-string<EmployeeExitCheck>> */
    private array $checks = [];

    /**
     * @param  class-string<EmployeeExitCheck>  $class
     */
    public function register(string $class): void
    {
        if (! in_array($class, $this->checks, true)) {
            $this->checks[] = $class;
        }
    }

    /**
     * @return list<ExitCheckItem>
     */
    public function run(Employee $employee): array
    {
        $items = [];

        foreach ($this->checks as $class) {
            /** @var EmployeeExitCheck $check */
            $check = app($class);
            array_push($items, ...$check->check($employee));
        }

        return $items;
    }

    public function blocks(Employee $employee): bool
    {
        foreach ($this->run($employee) as $item) {
            if ($item->blocking) {
                return true;
            }
        }

        return false;
    }
}
