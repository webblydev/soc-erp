<?php

namespace App\Modules\Hrm\Rules;

use App\Modules\Hrm\Models\Employee;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The value must be an employee in active employment (HR-BR-06), unless it is the value the
 * record already holds.
 */
final class AssignableEmployee implements ValidationRule
{
    public function __construct(private int|string|null $current = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            $fail(__('The selected :attribute is invalid.'));

            return;
        }

        if ($this->current !== null && (int) $value === (int) $this->current) {
            return;
        }

        if (! Employee::query()->assignable()->whereKey((int) $value)->exists()) {
            $fail(__('The selected :attribute is not an active employee.'));
        }
    }
}
