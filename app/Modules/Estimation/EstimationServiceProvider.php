<?php

namespace App\Modules\Estimation;

use App\Modules\Estimation\Models\CostCategory;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\EstimateKind;
use App\Modules\Estimation\Models\EstimateLine;
use App\Modules\Estimation\Models\EstimateMaterialLine;
use App\Modules\Estimation\Models\EstimateStatus;
use App\Modules\Estimation\Models\FindingCategory;
use App\Modules\Estimation\Models\FindingSeverity;
use App\Modules\Estimation\Models\FindingStatus;
use App\Modules\Estimation\Models\InspectionStatus;
use App\Modules\Estimation\Models\InspectionType;
use App\Modules\Estimation\Models\MbDirection;
use App\Modules\Estimation\Models\MbStatus;
use App\Modules\Estimation\Models\MeasurementEntry;
use App\Modules\Estimation\Models\ProjectBudgetLine;
use App\Modules\Estimation\Models\SiteInspection;
use App\Modules\Estimation\Models\SiteInspectionFinding;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class EstimationServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap Estimation & Site services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/estimation'));

        Relation::morphMap([
            'estimate_kind' => EstimateKind::class,
            'estimate_status' => EstimateStatus::class,
            'cost_category' => CostCategory::class,
            'mb_status' => MbStatus::class,
            'mb_direction' => MbDirection::class,
            'inspection_type' => InspectionType::class,
            'inspection_status' => InspectionStatus::class,
            'finding_category' => FindingCategory::class,
            'finding_severity' => FindingSeverity::class,
            'finding_status' => FindingStatus::class,
            'estimate' => Estimate::class,
            'estimate_line' => EstimateLine::class,
            'estimate_material_line' => EstimateMaterialLine::class,
            'project_budget_line' => ProjectBudgetLine::class,
            'measurement_entry' => MeasurementEntry::class,
            'site_inspection' => SiteInspection::class,
            'site_inspection_finding' => SiteInspectionFinding::class,
        ]);

        Livewire::addLocation(classNamespace: 'App\\Modules\\Estimation\\Livewire');
    }
}
