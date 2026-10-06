<?php

namespace App\Support\Imports;

use App\Support\Exports\QueryExport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * The rows an import skipped, with their sheet row number and errors, ready to fix and re-upload.
 */
final class FailedRowsExport implements FromArray, ShouldAutoSize, WithHeadings
{
    /**
     * @param  list<string>  $headings
     * @param  list<ImportRow>  $rows
     */
    public function __construct(private array $headings, private array $rows) {}

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['row', ...$this->headings, 'errors'];
    }

    /**
     * @return list<list<mixed>>
     */
    public function array(): array
    {
        return array_map(fn (ImportRow $row): array => [
            $row->number,
            ...array_map(fn (string $heading): mixed => QueryExport::neutralise($row->values[$heading] ?? ''), $this->headings),
            QueryExport::neutralise(implode('; ', $row->errors)),
        ], $this->rows);
    }
}
