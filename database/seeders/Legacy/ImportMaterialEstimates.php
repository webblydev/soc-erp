<?php

namespace Database\Seeders\Legacy;

use App\Modules\Catalog\Models\Material;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateKind;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

/**
 * v1 material estimates (tbl_project_material_estimate and its details) → approved MATERIAL
 * estimates (legacy seed spec L18). Catalog materials by v1 id or by name, else free text; the
 * quantity is the leading number of the v1 text, with the shifted qty / purpose row repaired.
 */
class ImportMaterialEstimates
{
    /** @var Collection<int, Material>|null */
    private ?Collection $materials = null;

    public function __construct(private LegacyEstimates $estimates) {}

    public function run(LegacyContext $context): int
    {
        $created = 0;
        $legacy = $context->legacy();

        foreach ($legacy->table('tbl_project_material_estimate')->where('status', 'a')->orderBy('Material_Estimate_SlNo')->get() as $row) {
            $ref = 'material_estimate:'.$row->Material_Estimate_SlNo;

            if (Estimate::withTrashed()->where('legacy_ref', $ref)->exists()) {
                continue;
            }

            $lines = $legacy->table('tbl_project_material_estimate_details')->where('material_estimate_id', (string) $row->Material_Estimate_SlNo)
                ->where('status', 'a')->orderBy('Estimate_Details_SlNo')->get();

            $done = DB::transaction(function () use ($context, $row, $ref, $lines): bool {
                $estimate = $this->estimates->header($context, $row, EstimateKind::MATERIAL, 'Material estimate', $ref);

                if ($estimate === null) {
                    return false;
                }

                foreach ($lines->values() as $index => $line) {
                    $estimate->materialLines()->create([...$this->line($line), 'sort_order' => $index + 1]);
                }

                return true;
            });

            $created += $done ? 1 : 0;
        }

        return $created;
    }

    /**
     * @return array<string, mixed>
     */
    private function line(stdClass $line): array
    {
        $material = $this->material((int) $line->material_id, $line->material_name);
        $qtyText = trim((string) $line->total_estimated_qty);
        $purpose = trim((string) $line->purpose_estimate);
        $quantity = LegacyMap::leadingNumber($qtyText);

        if ($quantity === null && $qtyText === '' && is_numeric($purpose)) {
            [$quantity, $purpose] = [$purpose, ''];
        } elseif ($quantity === null && $qtyText !== '') {
            $purpose = trim($purpose.' '.$qtyText);
        }

        $unitWord = trim((string) preg_replace('/^[\d.,\s]+/', '', $qtyText));
        $unit = $this->estimates->unitFor($line->unit) ?? $this->estimates->unitFor($unitWord) ?? $material->unit_id ?? $this->estimates->defaultUnit();
        $name = trim((string) $line->material_name);

        return [
            'material_id' => $material?->id,
            'material_name' => $material === null ? ($name !== '' ? Str::limit($name, 200, '') : 'Material') : null,
            'unit_id' => $unit,
            'estimated_qty' => $quantity ?? '0',
            'total_qty' => $quantity ?? '0',
            'amount' => 0,
            'purpose' => $purpose !== '' ? Str::limit($purpose, 255, '') : null,
        ];
    }

    private function material(int $legacyId, ?string $name): ?Material
    {
        $this->materials ??= Material::query()->get(['id', 'name', 'unit_id']);
        $target = LegacyMap::materialFor($legacyId) ?? $name;

        return $target === null ? null : $this->materials->first(fn (Material $material): bool => LegacyMap::nameKey($material->name) === LegacyMap::nameKey($target));
    }
}
