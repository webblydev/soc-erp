<?php

namespace App\Support\Exports;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Excel export of a query. Columns map a heading to an attribute path or a closure.
 */
final class QueryExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @param  Builder<covariant Model>  $query
     * @param  array<string, string|Closure>  $columns
     */
    public function __construct(private Builder $query, private array $columns) {}

    /**
     * @return Builder<covariant Model>
     */
    public function query(): Builder
    {
        return $this->query;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return array_keys($this->columns);
    }

    /**
     * @param  Model  $row
     * @return list<mixed>
     */
    public function map($row): array
    {
        return array_map(
            fn (string|Closure $column): mixed => $this->neutralise($column instanceof Closure ? $column($row) : data_get($row, $column)),
            array_values($this->columns),
        );
    }

    /**
     * Prefixes text that a spreadsheet would read as a formula (CSV/formula injection).
     */
    private function neutralise(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && str_contains("=+-@\t\r", $value[0])) {
            return "'".$value;
        }

        return $value;
    }
}
