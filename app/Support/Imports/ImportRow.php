<?php

namespace App\Support\Imports;

/**
 * One data row of an import file as judged by its definition.
 */
final readonly class ImportRow
{
    /**
     * @param  array<string, string>  $values  heading => cell text
     * @param  list<string>  $errors
     */
    public function __construct(
        public int $number,
        public array $values,
        public ImportRowStatus $status,
        public array $errors = [],
    ) {}
}
