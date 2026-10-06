<?php

namespace App\Support\Imports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * The blank import template: the definition's headings and one example row.
 */
final class TemplateExport implements FromArray, ShouldAutoSize, WithHeadings
{
    public function __construct(private ImportDefinition $definition) {}

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return array_keys($this->definition->columns());
    }

    /**
     * @return list<list<string>>
     */
    public function array(): array
    {
        $example = $this->definition->exampleRow();

        return [array_map(fn (string $heading): string => $example[$heading] ?? '', $this->headings())];
    }
}
