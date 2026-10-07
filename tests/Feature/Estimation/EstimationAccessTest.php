<?php

use App\Models\User;
use App\Modules\Crm\Models\Customer;
use App\Modules\Estimation\Models\Estimate;
use App\Modules\Estimation\Models\MeasurementEntry;
use App\Modules\Estimation\Models\SiteInspection;
use App\Modules\Estimation\Models\SiteInspectionFinding;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectEmployee;

beforeEach(function () {
    seedEstimation();
    seedAccessControl();
});

/**
 * A project with one estimate, MB entry, inspection and finding.
 *
 * @return array{project: Project, estimate: Estimate, entry: MeasurementEntry, inspection: SiteInspection, finding: SiteInspectionFinding}
 */
function estimationRecords(?Project $project = null): array
{
    $project ??= Project::factory()->create();
    $inspection = SiteInspection::factory()->onProject($project)->create();

    return [
        'project' => $project,
        'estimate' => Estimate::factory()->onProject($project)->create(),
        'entry' => MeasurementEntry::factory()->create(['project_id' => $project->id]),
        'inspection' => $inspection,
        'finding' => SiteInspectionFinding::factory()->forInspection($inspection)->create(),
    ];
}

function joinProject(User $user, Project $project): void
{
    ProjectEmployee::factory()->create(['project_id' => $project->id, 'employee_id' => $user->employee_id]);
}

test('an engineer sees the estimation records of their own projects only (spec E4)', function () {
    $engineer = staffUser('engineer');
    $own = estimationRecords();
    $other = estimationRecords();
    joinProject($engineer, $own['project']);

    expect(Estimate::query()->visibleTo($engineer)->pluck('id')->all())->toBe([$own['estimate']->id])
        ->and(MeasurementEntry::query()->visibleTo($engineer)->pluck('id')->all())->toBe([$own['entry']->id])
        ->and(SiteInspection::query()->visibleTo($engineer)->pluck('id')->all())->toBe([$own['inspection']->id])
        ->and(SiteInspectionFinding::query()->visibleTo($engineer)->pluck('id')->all())->toBe([$own['finding']->id])
        ->and($engineer->can('view', $own['estimate']))->toBeTrue()
        ->and($engineer->can('view', $other['estimate']))->toBeFalse()
        ->and($engineer->can('update', $other['entry']))->toBeFalse()
        ->and($engineer->can('view', $other['inspection']))->toBeFalse()
        ->and($engineer->can('createEstimate', $own['project']))->toBeTrue()
        ->and($engineer->can('createEstimate', $other['project']))->toBeFalse()
        ->and($engineer->can('recordMeasurement', $other['project']))->toBeFalse();
});

test('accountants see every estimate but cannot change one', function () {
    $accountant = staffUser('accountant');
    $records = estimationRecords();
    estimationRecords();

    expect(Estimate::query()->visibleTo($accountant)->count())->toBe(2)
        ->and($accountant->can('view', $records['estimate']))->toBeTrue()
        ->and($accountant->can('update', $records['estimate']))->toBeFalse()
        ->and($accountant->can('view', $records['entry']))->toBeTrue()
        ->and($accountant->can('verify', $records['entry']))->toBeFalse()
        ->and($accountant->can('viewBudget', $records['project']))->toBeTrue()
        ->and($accountant->can('manageBudget', $records['project']))->toBeFalse();
});

test('sales users see the estimates of their customers but not the site records', function () {
    $seller = staffUser('sales_executive');
    $customer = Customer::factory()->create(['account_manager_user_id' => $seller->id]);
    $theirs = estimationRecords(Project::factory()->forCustomer($customer)->create());
    estimationRecords();

    expect(Estimate::query()->visibleTo($seller)->pluck('id')->all())->toBe([$theirs['estimate']->id])
        ->and($seller->can('view', $theirs['estimate']))->toBeTrue()
        ->and($seller->can('view', $theirs['entry']))->toBeFalse()
        ->and($seller->can('viewSite', $theirs['project']))->toBeFalse();
});

test('only management or the project manager may approve, verify, close and manage the budget', function () {
    $pm = staffUser('project_manager');
    $memberPm = staffUser('project_manager');
    $management = staffUser('management');
    $records = estimationRecords(Project::factory()->managedBy(Employee::query()->findOrFail($pm->employee_id))->create());
    joinProject($memberPm, $records['project']);

    foreach ([[$pm, true], [$memberPm, false], [$management, true]] as [$user, $allowed]) {
        expect($user->can('approve', $records['estimate']))->toBe($allowed)
            ->and($user->can('verify', $records['entry']))->toBe($allowed)
            ->and($user->can('close', $records['inspection']))->toBe($allowed)
            ->and($user->can('manageBudget', $records['project']))->toBe($allowed)
            ->and($user->can('reopen', $records['finding']))->toBe($allowed)
            ->and($user->can('submit', $records['estimate']))->toBeTrue();
    }
});

test('a finding moves on by the responsible employee, not by another engineer', function () {
    $responsible = staffUser('engineer');
    $colleague = staffUser('engineer');
    $records = estimationRecords();
    joinProject($responsible, $records['project']);
    joinProject($colleague, $records['project']);
    $records['finding']->update(['responsible_type' => SiteInspectionFinding::RESPONSIBLE_EMPLOYEE, 'responsible_id' => $responsible->employee_id]);

    expect($responsible->can('changeStatus', $records['finding']))->toBeTrue()
        ->and($colleague->can('changeStatus', $records['finding']))->toBeFalse()
        ->and($responsible->can('reopen', $records['finding']))->toBeFalse();
});

test('engineers cannot change the MB rate, project managers can', function () {
    $engineer = staffUser('engineer');
    $pm = staffUser('project_manager');
    $project = Project::factory()->managedBy(Employee::query()->findOrFail($pm->employee_id))->create();
    joinProject($engineer, $project);

    expect($engineer->can('editMeasurementRate', $project))->toBeFalse()
        ->and($pm->can('editMeasurementRate', $project))->toBeTrue();
});
