<?php

namespace App\Support\Imports;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Validator;

/**
 * What one kind of record needs to be imported from a spreadsheet (spec C10). Rows are matched
 * by `code`; a matched row is updated, anything else is created.
 */
interface ImportDefinition
{
    /**
     * Template headings mapped to a short help label, in template order.
     *
     * @return array<string, string>
     */
    public function columns(): array;

    /**
     * @return list<string>
     */
    public function requiredHeadings(): array;

    /**
     * @return array<string, string>
     */
    public function exampleRow(): array;

    public function findExisting(string $code): ?Model;

    /**
     * Map a row onto Save Action input. Blank cells keep the existing record's values; cells that
     * cannot be resolved (unknown category, …) are reported per input field.
     *
     * @param  array<string, string>  $row
     * @return array{input: array<string, mixed>, errors: array<string, string>}
     */
    public function toInput(array $row, ?Model $existing): array;

    /**
     * @param  array<string, mixed>  $input
     */
    public function validator(array $input, ?Model $existing): Validator;

    /**
     * @param  array<string, mixed>  $input
     */
    public function save(array $input, ?Model $existing): Model;
}
