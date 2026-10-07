<?php

use App\Modules\Estimation\Jobs\NotifyOverdueFindings;
use App\Modules\Estimation\Jobs\SendMbVerificationDigest;
use App\Modules\Estimation\Models\FindingSeverity;
use App\Modules\Estimation\Models\FindingStatus;
use App\Modules\Estimation\Models\InspectionStatus;
use App\Modules\Estimation\Models\MbStatus;
use App\Modules\Estimation\Models\MeasurementEntry;
use App\Modules\Estimation\Models\SiteInspection;
use App\Modules\Estimation\Models\SiteInspectionFinding;
use App\Modules\Estimation\Notifications\FindingOverdue;
use App\Modules\Estimation\Notifications\MeasurementsAwaitingVerification;
use App\Modules\Foundation\Models\Setting;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use App\Support\Facades\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedEstimation();
    seedAccessControl();
    Notification::fake();
    Cache::flush();
    $this->pm = staffUser('project_manager');
    $this->engineer = staffUser('engineer');
    $this->project = Project::factory()->managedBy(Employee::query()->findOrFail($this->pm->employee_id))->create();
});

test('the PM gets one digest a day of entries waiting more than a day', function () {
    MeasurementEntry::factory()->count(2)->create(['project_id' => $this->project->id, 'created_at' => now()->subDays(2)]);
    MeasurementEntry::factory()->create(['project_id' => $this->project->id]);
    MeasurementEntry::factory()->withStatus(MbStatus::VERIFIED)->create(['project_id' => $this->project->id, 'created_at' => now()->subDays(2)]);

    (new SendMbVerificationDigest)->handle();
    (new SendMbVerificationDigest)->handle();

    Notification::assertSentToTimes($this->pm, MeasurementsAwaitingVerification::class, 1);
    Notification::assertSentTo($this->pm, MeasurementsAwaitingVerification::class, fn (MeasurementsAwaitingVerification $notification): bool => $notification->count === 2);
});

test('overdue findings are notified once per spell to the responsible employee and the PM', function () {
    $inspection = SiteInspection::factory()->onProject($this->project)->withStatus(InspectionStatus::SUBMITTED)->create();
    $finding = SiteInspectionFinding::factory()->forInspection($inspection)->severity(FindingSeverity::HIGH)->create([
        'responsible_type' => SiteInspectionFinding::RESPONSIBLE_EMPLOYEE, 'responsible_id' => $this->engineer->employee_id, 'due_date' => today()->subDay(),
    ]);
    SiteInspectionFinding::factory()->forInspection($inspection)->withStatus(FindingStatus::RESOLVED)->create(['due_date' => today()->subDay()]);

    (new NotifyOverdueFindings)->handle();
    (new NotifyOverdueFindings)->handle();

    Notification::assertSentToTimes($this->engineer, FindingOverdue::class, 1);
    Notification::assertSentToTimes($this->pm, FindingOverdue::class, 1);
    expect($finding->fresh()->overdue_notified_at)->not->toBeNull();
});

test('overdue finding alerts can be turned off', function () {
    Setting::query()->where('group', 'site')->where('key', 'finding_overdue_notify')->update(['value' => false]);
    Settings::flush();
    SiteInspectionFinding::factory()->forInspection(SiteInspection::factory()->onProject($this->project)->create())->create(['due_date' => today()->subDay()]);

    (new NotifyOverdueFindings)->handle();

    Notification::assertNothingSent();
});
