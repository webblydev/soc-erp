<?php

namespace Database\Seeders\Legacy;

use App\Modules\Catalog\Enums\MeasurementFormula;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateKind;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * v1 work estimates (tbl_work_estimate, tbl_work_estimate_details) → approved BOQ estimates with
 * manual quantities and no rates (legacy seed spec L17). Rows whose project was not imported are
 * skipped; imported rows are not touched again.
 */
class ImportWorkEstimates
{
    public function __construct(private LegacyEstimates $estimates) {}

    public function run(LegacyContext $context): int
    {
        $created = 0;
        $legacy = $context->legacy();

        foreach ($legacy->table('tbl_work_estimate')->where('status', 'a')->orderBy('Work_Estimate_SlNo')->get() as $row) {
            $ref = 'work_estimate:'.$row->Work_Estimate_SlNo;

            if (Estimate::withTrashed()->where('legacy_ref', $ref)->exists()) {
                continue;
            }

            $lines = $legacy->table('tbl_work_estimate_details')->where('work_estimate_id', (string) $row->Work_Estimate_SlNo)
                ->where('status', 'a')->orderBy('Work_Estimate_Details_SlNo')->get();

            $done = DB::transaction(function () use ($context, $row, $ref, $lines): bool {
                $estimate = $this->estimates->header($context, $row, EstimateKind::BOQ, 'Work estimate', $ref);

                if ($estimate === null) {
                    return false;
                }

                foreach ($lines->values() as $index => $line) {
                    $positive = fn (mixed $value): ?string => is_numeric($value) && (float) $value > 0 ? (string) $value : null;

                    $estimate->lines()->create([
                        'line_no' => '1.'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                        'description' => trim((string) $line->work_description) !== '' ? trim((string) $line->work_description) : 'Line',
                        'level' => Str::limit(trim((string) $line->level), 60, '') ?: null,
                        'location' => Str::limit(trim((string) $line->location), 120, '') ?: null,
                        'measurement_formula' => MeasurementFormula::Manual,
                        'nos' => $positive($line->nose) ?? '1',
                        'length' => $positive($line->length),
                        'width' => $positive($line->width),
                        'height' => $positive($line->height),
                        'unit_id' => $this->estimates->unitFor($line->unit) ?? $this->estimates->defaultUnit(),
                        'quantity' => is_numeric($line->quantity) ? (string) $line->quantity : '0',
                        'quantity_is_manual' => true,
                        'amount' => 0,
                        'remarks' => Str::limit(trim((string) $line->measurement), 255, '') ?: null,
                        'sort_order' => $index + 1,
                    ]);
                }

                return true;
            });

            $created += $done ? 1 : 0;
        }

        return $created;
    }
}
