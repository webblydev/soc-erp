<?php

namespace Database\Seeders\Projects;

use App\Modules\Projects\Models\ApprovalAuthority;
use App\Modules\Projects\Models\ApprovalStatus;
use App\Modules\Projects\Models\ApprovalType;
use App\Modules\Projects\Models\ContractStatus;
use App\Modules\Projects\Models\HoldReason;
use App\Modules\Projects\Models\ProjectPhase;
use App\Modules\Projects\Models\ProjectRole;
use App\Modules\Projects\Models\ProjectServiceStatus;
use App\Modules\Projects\Models\ProjectStatus;
use App\Modules\Projects\Models\ProjectType;
use App\Modules\Projects\Models\ScheduleStatus;
use App\Modules\Projects\Models\ScheduleTrigger;
use App\Modules\Projects\Models\TaskPriority;
use App\Modules\Projects\Models\TaskStatus;
use App\Modules\Projects\Models\TaskType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Projects lookups (docs/04 §3.1, spec §3.1). Existing rows keep admin edits; only missing rows
 * are created.
 */
class ProjectLookupSeeder extends Seeder
{
    private const SYSTEM = ['is_system' => true];

    public function run(): void
    {
        $this->seed(ProjectType::class, [
            ['RESIDENTIAL', 'Residential'], ['COMMERCIAL', 'Commercial'], ['MIXED_USE', 'Mixed Use'], ['INDUSTRIAL', 'Industrial'],
            ['INTERIOR', 'Interior'], ['RENOVATION', 'Renovation'], ['SURVEY', 'Survey'], ['ESTIMATE', 'Estimate (BLE / UP sheet)'],
            ['TRAINING', 'Training'], ['CONSULTANCY', 'Consultancy'],
            ['INTERNAL', 'Internal', ['is_internal' => true, 'is_billable' => false, ...self::SYSTEM]],
        ]);

        $this->seed(ProjectStatus::class, [
            ['ENQUIRY', 'Enquiry', [...self::SYSTEM, 'color' => 'neutral']],
            ['CONTRACTED', 'Contracted', [...self::SYSTEM, 'color' => 'info']],
            ['IN_PROGRESS', 'In Progress', [...self::SYSTEM, 'color' => 'info']],
            ['ON_HOLD', 'On Hold', [...self::SYSTEM, 'color' => 'warning']],
            ['HANDED_OVER', 'Handed Over', [...self::SYSTEM, 'color' => 'success']],
            ['COMPLETED', 'Completed', ['is_open' => false, 'is_closed' => true, ...self::SYSTEM, 'color' => 'success']],
            ['CANCELLED', 'Cancelled', ['is_open' => false, 'is_closed' => true, 'allows_billing' => false, 'allows_costing' => false, ...self::SYSTEM, 'color' => 'danger']],
        ]);

        $this->seed(ProjectPhase::class, [
            ['DESIGN', 'Design'], ['APPROVAL', 'Approval'], ['PRE_CONSTRUCTION', 'Pre-construction'], ['CONSTRUCTION', 'Construction'],
            ['FINISHING', 'Finishing'], ['HANDOVER', 'Handover'], ['DEFECT_LIABILITY', 'Defect Liability'],
        ]);

        $this->seed(ProjectRole::class, [
            ['PM', 'Project Manager', self::SYSTEM], ['SUPERVISOR', 'Supervisor', self::SYSTEM], ['ARCHITECT', 'Architect'],
            ['STRUCTURAL', 'Structural Engineer'], ['MEP', 'MEP Engineer'], ['SITE_ENGINEER', 'Site Engineer'], ['DRAFTSMAN', 'Draftsman'],
            ['SURVEYOR', 'Surveyor'], ['SUPPORT_OFFICER', 'Support Officer', self::SYSTEM], ['ACCOUNTS', 'Accounts'],
        ]);

        $this->seed(TaskType::class, [
            ['DESIGN', 'Design', ['default_estimated_hours' => 16]], ['DRAWING', 'Drawing', ['default_estimated_hours' => 16]],
            ['STRUCTURAL_CALC', 'Structural Calculation', ['default_estimated_hours' => 12]], ['APPROVAL_FILE', 'Approval File', ['default_estimated_hours' => 8]],
            ['SITE_VISIT', 'Site Visit', ['default_estimated_hours' => 4]], ['SURVEY', 'Survey', ['default_estimated_hours' => 6]],
            ['ESTIMATE', 'Estimate', ['default_estimated_hours' => 8]], ['CLIENT_MEETING', 'Client Meeting', ['default_estimated_hours' => 2]],
            ['PROCUREMENT', 'Procurement', ['default_estimated_hours' => 4]], ['ACCOUNTS', 'Accounts', ['default_estimated_hours' => 2]],
            ['HR_ADMIN', 'HR & Admin', ['default_estimated_hours' => 2]], ['SUPPLY_CHAIN', 'Supply Chain', ['default_estimated_hours' => 4]],
            ['INVENTORY', 'Inventory', ['default_estimated_hours' => 2]], ['LOGISTIC', 'Logistic', ['default_estimated_hours' => 2]],
            ['CUSTOMER_RELATION', 'Customer Relation', ['default_estimated_hours' => 2]], ['OTHER', 'Other', ['default_estimated_hours' => 2, ...self::SYSTEM]],
        ]);

        $this->seed(TaskStatus::class, [
            ['TODO', 'Pending', [...self::SYSTEM, 'color' => 'neutral']],
            ['IN_PROGRESS', 'On Progress', [...self::SYSTEM, 'color' => 'info']],
            ['REVIEW', 'In Review', [...self::SYSTEM, 'color' => 'warning']],
            ['BLOCKED', 'Blocked', [...self::SYSTEM, 'color' => 'danger']],
            ['DONE', 'Completed', ['is_done' => true, ...self::SYSTEM, 'color' => 'success']],
            ['CANCELLED', 'Cancelled', ['is_cancelled' => true, ...self::SYSTEM, 'color' => 'neutral']],
        ]);

        $this->seed(TaskPriority::class, [
            ['LOW', 'Low', ['color' => 'neutral']], ['NORMAL', 'Normal', [...self::SYSTEM, 'color' => 'info']],
            ['HIGH', 'High', ['color' => 'warning']], ['CRITICAL', 'Critical', ['color' => 'danger']],
        ]);

        $this->seed(ApprovalAuthority::class, [
            ['RAJUK', 'RAJUK'], ['DNCC', 'DNCC'], ['DSCC', 'DSCC'], ['GAZIPUR_CC', 'Gazipur City Corporation'], ['CDA', 'CDA'], ['KDA', 'KDA'],
            ['UNION_PARISHAD', 'Union Parishad'], ['PAURASHAVA', 'Paurashava'], ['FIRE_SERVICE', 'Fire Service'], ['DOE', 'Department of Environment'],
            ['CAAB', 'CAAB'], ['OTHER', 'Other'],
        ]);

        $this->seed(ApprovalType::class, [
            ['LUC', 'Land Use Clearance', ['typical_days' => 45, 'default_checklist' => "Land deed\nMutation\nDCR\nTax receipt\nNID of owner\nLocation map"]],
            ['BP', 'Building Construction Permit', ['typical_days' => 90, 'default_checklist' => "Land Use Clearance\nLand deed\nMutation\nDCR\nTax receipt\nNID of owner\nSoil report\nArchitectural drawings\nStructural drawings"]],
            ['OC', 'Occupancy Certificate', ['typical_days' => 60, 'default_checklist' => "Building permit\nCompletion report\nAs-built drawings\nFire NOC"]],
            ['FIRE_NOC', 'Fire NOC', ['typical_days' => 30]], ['ENV_CLEARANCE', 'Environmental Clearance', ['typical_days' => 60]],
            ['HEIGHT_CLEARANCE', 'Height Clearance', ['typical_days' => 30]], ['UP_SHEET', 'UP Sheet', ['typical_days' => 15]],
            ['BANK_VALUATION', 'Bank Valuation', ['typical_days' => 15]], ['OTHER', 'Other', ['typical_days' => 30]],
        ]);

        $this->seed(ApprovalStatus::class, [
            ['PREPARING', 'Preparing', [...self::SYSTEM, 'color' => 'neutral']],
            ['SUBMITTED', 'Submitted', [...self::SYSTEM, 'color' => 'info']],
            ['QUERY', 'Query raised', [...self::SYSTEM, 'color' => 'warning']],
            ['RESUBMITTED', 'Resubmitted', [...self::SYSTEM, 'color' => 'info']],
            ['APPROVED', 'Approved', ['is_final' => true, 'is_success' => true, ...self::SYSTEM, 'color' => 'success']],
            ['REJECTED', 'Rejected', ['is_final' => true, ...self::SYSTEM, 'color' => 'danger']],
            ['WITHDRAWN', 'Withdrawn', ['is_final' => true, ...self::SYSTEM, 'color' => 'neutral']],
        ]);

        $this->seed(HoldReason::class, $this->named(['Client payment pending', 'Client instruction', 'Approval pending', 'Land dispute', 'Other']));

        $this->seed(ProjectServiceStatus::class, [
            ['NOT_STARTED', 'Not started', [...self::SYSTEM, 'color' => 'neutral']], ['IN_PROGRESS', 'In progress', [...self::SYSTEM, 'color' => 'info']],
            ['DELIVERED', 'Delivered', [...self::SYSTEM, 'color' => 'success']], ['CANCELLED', 'Cancelled', [...self::SYSTEM, 'color' => 'danger']],
        ]);

        $this->seed(ContractStatus::class, [
            ['DRAFT', 'Draft', [...self::SYSTEM, 'color' => 'neutral']], ['SIGNED', 'Signed', [...self::SYSTEM, 'color' => 'success']],
            ['AMENDED', 'Amended', [...self::SYSTEM, 'color' => 'info']], ['TERMINATED', 'Terminated', [...self::SYSTEM, 'color' => 'danger']],
        ]);

        $this->seed(ScheduleTrigger::class, [
            ['DATE', 'On a date', self::SYSTEM], ['PHASE', 'When a phase is reached', self::SYSTEM], ['APPROVAL', 'When an approval is granted', self::SYSTEM],
            ['TASK', 'When a task is completed', self::SYSTEM], ['MANUAL', 'Manually', self::SYSTEM],
        ]);

        $this->seed(ScheduleStatus::class, [
            ['PENDING', 'Pending', [...self::SYSTEM, 'color' => 'neutral']], ['DUE', 'Due', [...self::SYSTEM, 'color' => 'warning']],
            ['INVOICED', 'Invoiced', [...self::SYSTEM, 'color' => 'info']], ['PAID', 'Paid', [...self::SYSTEM, 'color' => 'success']],
            ['CANCELLED', 'Cancelled', [...self::SYSTEM, 'color' => 'danger']],
        ]);
    }

    /**
     * @param  class-string<Model>  $model
     * @param  list<array{0: string, 1: string, 2?: array<string, mixed>}>  $rows
     */
    private function seed(string $model, array $rows): void
    {
        foreach ($rows as $index => $row) {
            $model::query()->withTrashed()->firstOrCreate(['code' => $row[0]], ['name' => $row[1], 'sort_order' => $index + 1, ...($row[2] ?? [])]);
        }
    }

    /**
     * Rows whose code is derived from the name (upper snake case).
     *
     * @param  list<string>  $names
     * @return list<array{0: string, 1: string}>
     */
    private function named(array $names): array
    {
        return array_map(fn (string $name): array => [Str::upper(Str::snake(Str::of($name)->replaceMatches('/[^A-Za-z ]/', ' ')->squish()->value())), $name], $names);
    }
}
