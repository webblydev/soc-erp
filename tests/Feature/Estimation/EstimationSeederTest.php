<?php

use App\Modules\Estimation\Models\CostCategory;
use App\Modules\Estimation\Models\EstimateKind;
use App\Modules\Estimation\Models\EstimateStatus;
use App\Modules\Estimation\Models\FindingSeverity;
use App\Modules\Estimation\Models\FindingStatus;
use App\Modules\Estimation\Models\InspectionType;
use App\Modules\Estimation\Models\MbStatus;
use App\Modules\Foundation\Models\Permission;
use App\Modules\Foundation\Models\Role;
use App\Support\Facades\Settings;
use Database\Seeders\Estimation\EstimationSeeder;

test('the Estimation seeder is idempotent and sets the system flags', function () {
    seedEstimation();
    $this->seed(EstimationSeeder::class);

    expect(EstimateKind::query()->count())->toBe(4)
        ->and(EstimateKind::query()->where('code', EstimateKind::MATERIAL)->first())->has_work_lines->toBeFalse()->has_material_lines->toBeTrue()
        ->and(EstimateKind::query()->where('is_customer_facing', true)->pluck('code')->sort()->values()->all())->toBe(['BLE', 'UP_SHEET'])
        ->and(EstimateStatus::query()->where('is_locked', true)->pluck('code')->sort()->values()->all())->toBe(['APPROVED', 'SUBMITTED', 'SUPERSEDED'])
        ->and(EstimateStatus::query()->where('is_approved', true)->pluck('code')->all())->toBe(['APPROVED'])
        ->and(CostCategory::query()->count())->toBe(9)
        ->and(MbStatus::query()->where('is_billable', true)->pluck('code')->all())->toBe(['VERIFIED'])
        ->and(MbStatus::query()->where('is_locked', true)->pluck('code')->all())->toBe(['BILLED'])
        ->and(InspectionType::query()->where('allowed_after_completion', true)->pluck('code')->sort()->values()->all())->toBe(['HANDOVER', 'SNAG'])
        ->and(FindingSeverity::query()->where('requires_follow_up', true)->pluck('code')->sort()->values()->all())->toBe(['CRITICAL', 'HIGH'])
        ->and(FindingStatus::query()->where('is_closed', true)->pluck('code')->sort()->values()->all())->toBe(['ACCEPTED', 'RESOLVED']);
});

test('the Estimation and Site settings are seeded', function () {
    seedEstimation();

    expect(Settings::get('estimation.pm_approval_limit'))->toBe(5000000)
        ->and(Settings::get('estimation.auto_budget_from_approved_estimate'))->toBeTrue()
        ->and(Settings::get('site.mb_requires_verification'))->toBeTrue()
        ->and(Settings::get('site.mb_allow_exceed_boq_pct'))->toBe(10)
        ->and(Settings::get('site.finding_overdue_notify'))->toBeTrue();
});

test('estimation and site permissions are seeded and granted per spec §4.1', function () {
    seedAccessControl();

    $grants = fn (string $role): array => Role::query()->where('code', $role)->firstOrFail()
        ->permissions()->whereIn('module', ['estimation', 'site'])->pluck('name')->all();

    expect(Permission::query()->whereIn('module', ['estimation', 'site'])->count())->toBe(27)
        ->and($grants('management'))->toHaveCount(27)
        ->and($grants('project_manager'))->toContain('estimation.estimates.approve', 'estimation.budget.manage', 'site.mb.verify', 'site.mb.edit_rate')
        ->not->toContain('estimation.master_data.update')
        ->and($grants('engineer'))->toContain('estimation.estimates.submit', 'site.mb.create', 'site.inspections.close_finding')
        ->not->toContain('estimation.estimates.approve', 'site.mb.verify', 'site.mb.edit_rate', 'estimation.budget.manage')
        ->and($grants('accountant'))->toEqualCanonicalizing(['estimation.estimates.view', 'estimation.estimates.print', 'estimation.estimates.export', 'estimation.budget.view', 'site.mb.view', 'site.inspections.view'])
        ->and($grants('sales_executive'))->toEqualCanonicalizing(['estimation.estimates.view', 'estimation.estimates.print']);
});
