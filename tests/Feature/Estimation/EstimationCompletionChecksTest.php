<?php

use App\Modules\Estimation\Models\FindingSeverity;
use App\Modules\Estimation\Models\FindingStatus;
use App\Modules\Estimation\Models\MbStatus;
use App\Modules\Estimation\Models\MeasurementEntry;
use App\Modules\Estimation\Models\SiteInspection;
use App\Modules\Estimation\Models\SiteInspectionFinding;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Services\ProjectCompletionChecks;

beforeEach(fn () => seedEstimation());

test('open serious findings and unverified measurements block completion (spec E22)', function () {
    $project = Project::factory()->create();
    $inspection = SiteInspection::factory()->onProject($project)->create();
    SiteInspectionFinding::factory()->forInspection($inspection)->severity(FindingSeverity::CRITICAL)->create();
    SiteInspectionFinding::factory()->forInspection($inspection)->create();
    SiteInspectionFinding::factory()->forInspection($inspection)->severity(FindingSeverity::HIGH)->withStatus(FindingStatus::RESOLVED)->create();
    MeasurementEntry::factory()->count(2)->create(['project_id' => $project->id]);
    MeasurementEntry::factory()->withStatus(MbStatus::VERIFIED)->create(['project_id' => $project->id]);

    expect(app(ProjectCompletionChecks::class)->run($project))
        ->toContain('1 high or critical site finding is still open.', '2 measurements are not verified yet.');
});

test('a project with settled site records passes the estimation checks', function () {
    $project = Project::factory()->create();
    MeasurementEntry::factory()->withStatus(MbStatus::REJECTED)->create(['project_id' => $project->id]);

    expect(app(ProjectCompletionChecks::class)->run($project))->toBe([]);
});
