<?php

namespace App\Modules\Estimation\Exports;

use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateLine;
use App\Support\Exports\QueryExport;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * An estimate's work lines in the layout ImportEstimateLines reads back (spec E20).
 */
final class EstimateLinesExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    public const HEADINGS = ['Section', 'Line no', 'Work item code', 'Description', 'Level', 'Location', 'Formula', 'Nos', 'Length', 'Width', 'Height', 'Deduct', 'Unit', 'Quantity', 'Rate', 'Amount', 'Cost category', 'Remarks'];

    public function __construct(private Estimate $estimate) {}

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return self::HEADINGS;
    }

    /**
     * @return Collection<int, list<mixed>>
     */
    public function collection(): Collection
    {
        return $this->estimate->lines()->with(['section', 'workItem', 'unit', 'costCategory'])->get()
            ->map(fn (EstimateLine $line): array => array_map(QueryExport::neutralise(...), [
                $line->section?->name, $line->line_no, $line->workItem?->code, $line->description, $line->level, $line->location,
                $line->measurement_formula->value, $line->nos, $line->length, $line->width, $line->height, $line->deduction ? 'yes' : '',
                $line->unit->code, $line->quantity, $line->rate, $line->amount, $line->costCategory?->code, $line->remarks,
            ]));
    }
}
