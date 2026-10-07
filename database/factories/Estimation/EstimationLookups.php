<?php

namespace Database\Factories\Estimation;

use App\Modules\Estimation\Models\EstimateKind;
use App\Modules\Estimation\Models\EstimateStatus;
use App\Modules\Estimation\Models\FindingSeverity;
use App\Modules\Estimation\Models\FindingStatus;
use App\Modules\Estimation\Models\InspectionStatus;
use App\Modules\Estimation\Models\InspectionType;
use App\Modules\Estimation\Models\MbDirection;
use App\Modules\Estimation\Models\MbStatus;
use Database\Factories\Projects\ResolvesLookups;

/**
 * Seeded Estimation lookup rows by code, with the seeder's flags, created on the fly when the
 * Estimation seeder has not run.
 */
final class EstimationLookups
{
    use ResolvesLookups;

    private const KIND_FLAGS = [
        EstimateKind::BOQ => ['has_work_lines' => true, 'has_material_lines' => true],
        EstimateKind::MATERIAL => ['has_work_lines' => false, 'has_material_lines' => true],
        EstimateKind::BLE => ['has_work_lines' => true, 'is_customer_facing' => true],
        EstimateKind::UP_SHEET => ['has_work_lines' => true, 'is_customer_facing' => true],
    ];

    private const ESTIMATE_STATUS_FLAGS = [
        EstimateStatus::SUBMITTED => ['is_locked' => true],
        EstimateStatus::APPROVED => ['is_locked' => true, 'is_approved' => true],
        EstimateStatus::SUPERSEDED => ['is_locked' => true],
    ];

    private const MB_STATUS_FLAGS = [
        MbStatus::VERIFIED => ['is_billable' => true],
        MbStatus::BILLED => ['is_locked' => true],
    ];

    private const SEVERITY_FLAGS = [
        FindingSeverity::HIGH => ['requires_follow_up' => true],
        FindingSeverity::CRITICAL => ['requires_follow_up' => true],
    ];

    private const FINDING_STATUS_FLAGS = [
        FindingStatus::RESOLVED => ['is_closed' => true],
        FindingStatus::ACCEPTED => ['is_closed' => true],
    ];

    private const INSPECTION_TYPE_FLAGS = [
        InspectionType::HANDOVER => ['allowed_after_completion' => true],
        InspectionType::SNAG => ['allowed_after_completion' => true],
    ];

    public static function kind(string $code): int
    {
        return self::lookupId(EstimateKind::class, $code, self::KIND_FLAGS[$code] ?? []);
    }

    public static function estimateStatus(string $code): int
    {
        return self::lookupId(EstimateStatus::class, $code, self::ESTIMATE_STATUS_FLAGS[$code] ?? []);
    }

    public static function mbStatus(string $code): int
    {
        return self::lookupId(MbStatus::class, $code, self::MB_STATUS_FLAGS[$code] ?? []);
    }

    public static function mbDirection(string $code): int
    {
        return self::lookupId(MbDirection::class, $code);
    }

    public static function inspectionType(string $code): int
    {
        return self::lookupId(InspectionType::class, $code, self::INSPECTION_TYPE_FLAGS[$code] ?? []);
    }

    public static function inspectionStatus(string $code): int
    {
        return self::lookupId(InspectionStatus::class, $code);
    }

    public static function severity(string $code): int
    {
        return self::lookupId(FindingSeverity::class, $code, self::SEVERITY_FLAGS[$code] ?? []);
    }

    public static function findingStatus(string $code): int
    {
        return self::lookupId(FindingStatus::class, $code, self::FINDING_STATUS_FLAGS[$code] ?? []);
    }
}
