<?php

namespace App\Support\Lookups;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The value must be an active row of a lookup table (CM-BR-03: inactive rows are hidden from new
 * entries), unless it is the value the record already holds. An optional constraint narrows the
 * allowed rows further (e.g. non-internal business lines).
 */
final class ActiveLookup implements ValidationRule
{
    /**
     * @param  (Closure(Builder): mixed)|null  $constraint
     */
    public function __construct(
        private string $table,
        private int|string|null $current = null,
        private ?Closure $constraint = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            $fail(__('The selected :attribute is invalid.'));

            return;
        }

        if ($this->current !== null && (int) $value === (int) $this->current) {
            return;
        }

        $query = DB::table($this->table)->where('id', (int) $value)->where('is_active', true)->whereNull('deleted_at');

        if ($this->constraint !== null) {
            ($this->constraint)($query);
        }

        if (! $query->exists()) {
            $fail(__('The selected :attribute is invalid.'));
        }
    }
}
