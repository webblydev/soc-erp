<?php

use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateKind;
use App\Modules\Estimation\Models\EstimateStatus;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use Database\Seeders\Catalog\CatalogSeeder;
use Database\Seeders\Estimation\EstimationSeeder;
use Database\Seeders\Legacy\ImportMaterialEstimates;
use Database\Seeders\Legacy\ImportWorkEstimates;
use Database\Seeders\Legacy\LegacyContext;

beforeEach(function () {
    useLegacyDatabase();
    seedProjects();
    $this->seed([CatalogSeeder::class, EstimationSeeder::class]);
    superAdmin(['username' => 'admin']);
    $this->pm = Employee::factory()->create();
    $this->project = Project::factory()->managedBy($this->pm)->create(['name' => 'Md. Shohidul Islam, Satarkul', 'legacy_project_ref' => 54]);
    $this->context = new LegacyContext;
    $this->context->projects = [54 => $this->project->id];
});

function runEstimateImporters(LegacyContext $context): void
{
    app(ImportWorkEstimates::class)->run($context);
    app(ImportMaterialEstimates::class)->run($context);
}

test('a v1 work estimate becomes an approved BOQ with manual lines and no budget (L17)', function () {
    legacyRow('tbl_work_estimate', ['Work_Estimate_SlNo' => 4, 'project_id' => '54', 'work_name' => '54', 'address' => 'huikn', 'date' => '2024-06-11', 'AddTime' => '2024-06-11 15:36:59']);
    legacyRow('tbl_work_estimate', ['Work_Estimate_SlNo' => 3, 'project_id' => '54', 'date' => '2024-03-07', 'status' => 'd']);
    legacyRow('tbl_work_estimate_details', ['Work_Estimate_Details_SlNo' => 16, 'work_estimate_id' => '4', 'work_description' => 'plaster', 'level' => '1', 'location' => 'm.bed', 'measurement' => "12'x6''", 'unit' => 'sft', 'quantity' => 72]);
    legacyRow('tbl_work_estimate_details', ['Work_Estimate_Details_SlNo' => 17, 'work_estimate_id' => '4', 'work_description' => 'Gone', 'unit' => 'sft', 'quantity' => 1, 'status' => 'd']);

    runEstimateImporters($this->context);

    $estimate = Estimate::query()->sole();
    $line = $estimate->lines->sole();

    expect($estimate)
        ->title->toBe('Work estimate — Md. Shohidul Islam, Satarkul')
        ->site_address->toBe('huikn')
        ->legacy_ref->toBe('work_estimate:4')
        ->prepared_by->toBe($this->pm->id)
        ->and($estimate->kind->code)->toBe(EstimateKind::BOQ)
        ->and($estimate->status->code)->toBe(EstimateStatus::APPROVED)
        ->and($estimate->estimate_date->toDateString())->toBe('2024-06-11')
        ->and($line)->description->toBe('plaster')->quantity->toBe('72.0000')->quantity_is_manual->toBeTrue()
        ->remarks->toBe("12'x6''")->nos->toBe('1.0000')->length->toBeNull()
        ->and($line->unit->code)->toBe('sft')
        ->and($this->project->budgetLines()->count())->toBe(0);
});

test('v1 material estimates map catalog materials, units and the shifted quantity (L18)', function () {
    legacyRow('tbl_project_material_estimate', ['Material_Estimate_SlNo' => 6, 'project_id' => '54', 'address' => 'Satarkul, Badda, Dhaka', 'date' => '2024-05-22', 'AddTime' => '2024-05-22 15:56:23']);
    legacyRow('tbl_project_material_estimate_details', ['Estimate_Details_SlNo' => 27, 'material_estimate_id' => '6', 'material_name' => '16mm Rebar', 'unit' => 'Ton', 'total_estimated_qty' => '', 'purpose_estimate' => '2.0']);
    legacyRow('tbl_project_material_estimate_details', ['Estimate_Details_SlNo' => 32, 'material_estimate_id' => '6', 'material_name' => 'Cement', 'unit' => 'Bag', 'total_estimated_qty' => '350']);
    legacyRow('tbl_project_material_estimate_details', ['Estimate_Details_SlNo' => 34, 'material_estimate_id' => '6', 'material_id' => 4, 'unit' => 'Shah cement ', 'total_estimated_qty' => '10 bag']);
    legacyRow('tbl_project_material_estimate_details', ['Estimate_Details_SlNo' => 35, 'material_estimate_id' => '6', 'material_id' => 6, 'unit' => 'CFT', 'total_estimated_qty' => '450']);
    legacyRow('tbl_project_material_estimate_details', ['Estimate_Details_SlNo' => 36, 'material_estimate_id' => '6', 'material_name' => 'Rods', 'unit' => 'parsec', 'total_estimated_qty' => 'some']);

    runEstimateImporters($this->context);

    $lines = Estimate::query()->sole()->materialLines()->with(['material', 'unit'])->get();

    expect($lines->map(fn ($line) => [$line->displayName(), $line->estimated_qty, $line->unit->code, $line->purpose])->all())->toBe([
        ['16mm Rebar', '2.0000', 'ton', null],
        ['Cement', '350.0000', 'bag', null],
        ['Grey Cement (PCC)', '10.0000', 'bag', null],
        ['Local Sand (FM-2.0)', '450.0000', 'cft', null],
        ['Rods', '0.0000', 'nos', 'some'],
    ])->and($lines[2]->material_id)->not->toBeNull()
        ->and($lines[0]->material_id)->toBeNull();
});

test('estimates of projects that were not imported are skipped, and a re-run adds nothing', function () {
    legacyRow('tbl_work_estimate', ['Work_Estimate_SlNo' => 4, 'project_id' => '54', 'date' => '2024-06-11']);
    legacyRow('tbl_work_estimate', ['Work_Estimate_SlNo' => 5, 'project_id' => '999', 'date' => '2024-06-11']);
    legacyRow('tbl_project_material_estimate', ['Material_Estimate_SlNo' => 7, 'project_id' => '54', 'date' => '2025-04-23']);

    runEstimateImporters($this->context);
    runEstimateImporters($this->context);

    expect(Estimate::query()->pluck('legacy_ref')->sort()->values()->all())->toBe(['material_estimate:7', 'work_estimate:4']);
});
