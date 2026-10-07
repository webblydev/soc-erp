<?php

namespace Database\Seeders\Estimation;

use App\Modules\Estimation\Models\CostCategory;
use App\Modules\Estimation\Models\EstimateKind;
use App\Modules\Estimation\Models\EstimateStatus;
use App\Modules\Estimation\Models\FindingCategory;
use App\Modules\Estimation\Models\FindingSeverity;
use App\Modules\Estimation\Models\FindingStatus;
use App\Modules\Estimation\Models\InspectionStatus;
use App\Modules\Estimation\Models\InspectionType;
use App\Modules\Estimation\Models\MbDirection;
use App\Modules\Estimation\Models\MbStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * Estimation & Site lookups (docs/05 §3.1, spec §3.1). Existing rows keep admin edits; only missing
 * rows are created.
 */
class EstimationLookupSeeder extends Seeder
{
    private const SYSTEM = ['is_system' => true];

    public function run(): void
    {
        $this->seed(EstimateKind::class, [
            ['BOQ', 'Work Estimate / BOQ', ['has_work_lines' => true, 'has_material_lines' => true, ...self::SYSTEM]],
            ['MATERIAL', 'Material Estimate', ['has_work_lines' => false, 'has_material_lines' => true, ...self::SYSTEM]],
            ['BLE', 'Bank Loan Estimate', ['has_work_lines' => true, 'is_customer_facing' => true, ...self::SYSTEM]],
            ['UP_SHEET', 'Union Parishad Sheet', ['has_work_lines' => true, 'is_customer_facing' => true, ...self::SYSTEM]],
        ]);

        $this->seed(EstimateStatus::class, [
            ['DRAFT', 'Draft', [...self::SYSTEM, 'color' => 'neutral']],
            ['SUBMITTED', 'Submitted', ['is_locked' => true, ...self::SYSTEM, 'color' => 'info']],
            ['APPROVED', 'Approved', ['is_locked' => true, 'is_approved' => true, ...self::SYSTEM, 'color' => 'success']],
            ['REJECTED', 'Rejected', [...self::SYSTEM, 'color' => 'danger']],
            ['SUPERSEDED', 'Superseded', ['is_locked' => true, ...self::SYSTEM, 'color' => 'neutral']],
        ]);

        $this->seed(CostCategory::class, [
            ['MATERIAL', 'Material', self::SYSTEM], ['LABOUR', 'Labour'], ['SUBCONTRACT', 'Subcontract'], ['EQUIPMENT', 'Equipment'],
            ['TRANSPORT', 'Transport'], ['APPROVAL_FEE', 'Approval fee'], ['CONSULTANT', 'Consultant'], ['OVERHEAD', 'Overhead'],
            ['OTHER', 'Other', self::SYSTEM],
        ]);

        $this->seed(MbStatus::class, [
            ['RECORDED', 'Recorded', [...self::SYSTEM, 'color' => 'neutral']],
            ['VERIFIED', 'Verified', ['is_billable' => true, ...self::SYSTEM, 'color' => 'success']],
            ['BILLED', 'Billed', ['is_locked' => true, ...self::SYSTEM, 'color' => 'info']],
            ['REJECTED', 'Rejected', [...self::SYSTEM, 'color' => 'danger']],
        ]);

        $this->seed(MbDirection::class, [
            ['CUSTOMER', 'Customer (SOC bills the client)', self::SYSTEM],
            ['VENDOR', 'Vendor (the vendor bills SOC)', self::SYSTEM],
        ]);

        $this->seed(InspectionType::class, [
            ['WEEKLY', 'Weekly'], ['EVENT', 'Event-based'], ['CASTING', 'Pour / Casting'], ['MATERIAL', 'Material check'],
            ['HANDOVER', 'Handover', ['allowed_after_completion' => true, ...self::SYSTEM]],
            ['SNAG', 'Snag list', ['allowed_after_completion' => true, ...self::SYSTEM]],
        ]);

        $this->seed(InspectionStatus::class, [
            ['DRAFT', 'Draft', [...self::SYSTEM, 'color' => 'neutral']],
            ['SUBMITTED', 'Submitted', [...self::SYSTEM, 'color' => 'info']],
            ['CLOSED', 'Closed', [...self::SYSTEM, 'color' => 'success']],
        ]);

        $this->seed(FindingCategory::class, [
            ['QUALITY', 'Quality'], ['SAFETY', 'Safety'], ['PROGRESS', 'Progress'], ['MATERIAL', 'Material'],
            ['DESIGN_DEVIATION', 'Design deviation'], ['WORKMANSHIP', 'Workmanship'], ['OTHER', 'Other'],
        ]);

        $this->seed(FindingSeverity::class, [
            ['LOW', 'Low', [...self::SYSTEM, 'color' => 'neutral']],
            ['MEDIUM', 'Medium', [...self::SYSTEM, 'color' => 'info']],
            ['HIGH', 'High', ['requires_follow_up' => true, ...self::SYSTEM, 'color' => 'warning']],
            ['CRITICAL', 'Critical', ['requires_follow_up' => true, ...self::SYSTEM, 'color' => 'danger']],
        ]);

        $this->seed(FindingStatus::class, [
            ['OPEN', 'Open', [...self::SYSTEM, 'color' => 'warning']],
            ['IN_PROGRESS', 'In progress', [...self::SYSTEM, 'color' => 'info']],
            ['RESOLVED', 'Resolved', ['is_closed' => true, ...self::SYSTEM, 'color' => 'success']],
            ['ACCEPTED', 'Accepted (no action)', ['is_closed' => true, ...self::SYSTEM, 'color' => 'neutral']],
        ]);
    }

    /**
     * @param  class-string<Model>  $model
     * @param  list<array{0: string, 1: string, 2?: array<string, mixed>}>  $rows
     */
    private function seed(string $model, array $rows): void
    {
        foreach ($rows as $index => $row) {
            $model::query()->withoutGlobalScopes()->firstOrCreate(['code' => $row[0]], ['name' => $row[1], 'sort_order' => $index + 1, ...($row[2] ?? [])]);
        }
    }
}
