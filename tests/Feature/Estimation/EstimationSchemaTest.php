<?php

use App\Modules\Catalog\Enums\MeasurementFormula;
use App\Modules\Catalog\Models\Material;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateKind;
use App\Modules\Estimation\Models\EstimateLine;
use App\Modules\Estimation\Models\EstimateStatus;
use App\Modules\Estimation\Models\FindingSeverity;
use App\Modules\Estimation\Models\FindingStatus;
use App\Modules\Estimation\Models\MbStatus;
use App\Modules\Estimation\Models\MeasurementEntry;
use App\Modules\Estimation\Models\SiteInspection;
use App\Modules\Estimation\Models\SiteInspectionFinding;
use App\Modules\Projects\Models\Project;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;

test('an estimate belongs to its project, kind and status and has lines', function () {
    $estimate = Estimate::factory()->ofKind(EstimateKind::MATERIAL)->withLines(2)->create();

    expect($estimate->project)->toBeInstanceOf(Project::class)
        ->and($estimate->kind->code)->toBe(EstimateKind::MATERIAL)
        ->and($estimate->hasWorkLines())->toBeFalse()
        ->and($estimate->hasMaterialLines())->toBeTrue()
        ->and($estimate->status->code)->toBe(EstimateStatus::DRAFT)
        ->and($estimate->isLocked())->toBeFalse()
        ->and($estimate->lines)->toHaveCount(2)
        ->and($estimate->lines->first()->measurement_formula)->toBe(MeasurementFormula::NosLW)
        ->and($estimate->total_amount)->toBe('10000.00')
        ->and($estimate->getRouteKeyName())->toBe('estimate_number')
        ->and($estimate->project->estimates()->count())->toBe(1);
});

test('revisions share a root and only the newest counts as the latest revision', function () {
    $original = Estimate::factory()->approved()->create();
    $revision = Estimate::factory()->revisionOf($original)->create();

    expect($revision->root->is($original))->toBeTrue()
        ->and($revision->estimate_number)->toBe($original->estimate_number.'-R1')
        ->and($original->revisions->pluck('id')->all())->toBe([$revision->id])
        ->and($original->isLatestRevision())->toBeFalse()
        ->and($revision->isLatestRevision())->toBeTrue()
        ->and(Estimate::query()->latestRevisions()->pluck('id')->all())->toBe([$revision->id])
        ->and(Estimate::query()->familyOf($revision)->count())->toBe(2)
        ->and($original->isLocked())->toBeTrue();
});

test('a material line names a catalog material or a free-text material', function () {
    $estimate = Estimate::factory()->ofKind(EstimateKind::MATERIAL)->create();
    $unit = Unit::factory()->create();
    $cement = Material::factory()->create(['name' => 'Grey Cement (OPC)']);

    $catalog = $estimate->materialLines()->create(['material_id' => $cement->id, 'unit_id' => $unit->id, 'estimated_qty' => 350]);
    $typed = $estimate->materialLines()->create(['material_name' => '16mm Rebar', 'unit_id' => $unit->id, 'estimated_qty' => 2]);

    expect($catalog->displayName())->toBe('Grey Cement (OPC)')
        ->and($typed->displayName())->toBe('16mm Rebar');
});

test('a measurement entry follows its BOQ line and its billable flag', function () {
    $line = EstimateLine::factory()->create();
    $entry = MeasurementEntry::factory()->forLine($line)->withStatus(MbStatus::VERIFIED)->create();

    expect($entry->estimateLine->is($line))->toBeTrue()
        ->and($entry->project_id)->toBe($line->estimate->project_id)
        ->and($entry->isBillable())->toBeTrue()
        ->and($entry->getRouteKeyName())->toBe('mb_number')
        ->and($line->measurements()->count())->toBe(1);

    $entry->forceFill(['running_bill_line_id' => 99])->save();

    expect($entry->fresh()->isBillable())->toBeFalse()
        ->and($entry->fresh()->isLocked())->toBeTrue();
});

test('the work order and running bill hooks have no foreign keys yet', function () {
    $keys = collect(Schema::getForeignKeys('measurement_entries'))->flatMap(fn (array $key): array => $key['columns'])->all();

    expect($keys)->not->toContain('work_order_id', 'work_order_item_id', 'running_bill_line_id')
        ->and(collect(Schema::getForeignKeys('site_inspections'))->flatMap(fn (array $key): array => $key['columns'])->all())
        ->not->toContain('contractor_vendor_id');
});

test('an inspection has findings with open, serious and overdue scopes', function () {
    $inspection = SiteInspection::factory()->create();
    $overdue = SiteInspectionFinding::factory()->forInspection($inspection)->severity(FindingSeverity::HIGH)->create(['due_date' => today()->subDay()]);
    SiteInspectionFinding::factory()->forInspection($inspection)->withStatus(FindingStatus::RESOLVED)->create(['due_date' => today()->subDay()]);
    SiteInspectionFinding::factory()->forInspection($inspection)->create();

    expect($inspection->findings)->toHaveCount(3)
        ->and($inspection->getRouteKeyName())->toBe('inspection_number')
        ->and(SiteInspectionFinding::query()->open()->count())->toBe(2)
        ->and(SiteInspectionFinding::query()->overdue()->pluck('id')->all())->toBe([$overdue->id])
        ->and(SiteInspectionFinding::query()->serious()->pluck('id')->all())->toBe([$overdue->id])
        ->and($overdue->isOverdue())->toBeTrue()
        ->and($overdue->requiresFollowUp())->toBeTrue()
        ->and($inspection->project->siteInspections()->count())->toBe(1);
});

test('the estimation models are in the morph map', function () {
    expect(Relation::getMorphedModel('estimate'))->toBe(Estimate::class)
        ->and(Relation::getMorphedModel('measurement_entry'))->toBe(MeasurementEntry::class)
        ->and(Relation::getMorphedModel('site_inspection_finding'))->toBe(SiteInspectionFinding::class);
});
