<?php

namespace App\Modules\Estimation;

use App\Modules\Estimation\Events\EstimateApproved;
use App\Modules\Estimation\Listeners\BuildBudgetOnApproval;
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
use App\Modules\Estimation\Policies\EstimatePolicy;
use App\Modules\Estimation\Policies\MeasurementEntryPolicy;
use App\Modules\Estimation\Policies\ProjectEstimationPolicy;
use App\Modules\Estimation\Policies\SiteInspectionFindingPolicy;
use App\Modules\Estimation\Policies\SiteInspectionPolicy;
use App\Modules\Estimation\Services\BudgetCostSources;
use App\Modules\Estimation\Services\CompletionChecks\OpenFindingsCheck;
use App\Modules\Estimation\Services\CompletionChecks\UnverifiedMeasurementsCheck;
use App\Modules\Projects\Services\ProjectCompletionChecks;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class EstimationServiceProvider extends ServiceProvider
{
    /**
     * Register Estimation services. Purchases and Accounting add their cost sources (spec E2).
     */
    public function register(): void
    {
        $this->app->singleton(BudgetCostSources::class);

        $this->app->resolving(ProjectCompletionChecks::class, function (ProjectCompletionChecks $checks): void {
            $checks->register(OpenFindingsCheck::class);
            $checks->register(UnverifiedMeasurementsCheck::class);
        });
    }

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

        Gate::policy(Estimate::class, EstimatePolicy::class);
        Gate::policy(MeasurementEntry::class, MeasurementEntryPolicy::class);
        Gate::policy(SiteInspection::class, SiteInspectionPolicy::class);
        Gate::policy(SiteInspectionFinding::class, SiteInspectionFindingPolicy::class);

        foreach (ProjectEstimationPolicy::ABILITIES as $ability) {
            Gate::define($ability, [ProjectEstimationPolicy::class, $ability]);
        }

        Event::listen(EstimateApproved::class, BuildBudgetOnApproval::class);

        Livewire::addLocation(classNamespace: 'App\\Modules\\Estimation\\Livewire');
    }
}
