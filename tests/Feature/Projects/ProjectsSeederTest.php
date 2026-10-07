<?php

use App\Modules\Foundation\Models\Permission;
use App\Modules\Foundation\Models\Role;
use App\Modules\Projects\Models\ApprovalStatus;
use App\Modules\Projects\Models\ApprovalType;
use App\Modules\Projects\Models\ProjectStatus;
use App\Modules\Projects\Models\ProjectType;
use App\Modules\Projects\Models\TaskStatus;
use App\Modules\Projects\Models\TaskTemplate;
use App\Support\Facades\Settings;
use Database\Seeders\Projects\ProjectsSeeder;
use Database\Seeders\Projects\TaskTemplateSeeder;

test('the Projects seeder is idempotent and sets the system flags', function () {
    seedProjects();
    $this->seed(ProjectsSeeder::class);

    expect(ProjectStatus::query()->count())->toBe(7)
        ->and(ProjectStatus::query()->where('is_closed', true)->pluck('code')->sort()->values()->all())->toBe(['CANCELLED', 'COMPLETED'])
        ->and(ProjectStatus::query()->where('code', 'CANCELLED')->first())->allows_billing->toBeFalse()->allows_costing->toBeFalse()
        ->and(ProjectType::query()->where('code', 'INTERNAL')->first())->is_internal->toBeTrue()->is_billable->toBeFalse()
        ->and(TaskStatus::query()->where('is_done', true)->pluck('code')->all())->toBe(['DONE'])
        ->and(ApprovalStatus::query()->where('is_final', true)->pluck('code')->sort()->values()->all())->toBe(['APPROVED', 'REJECTED', 'WITHDRAWN'])
        ->and(ApprovalType::query()->where('code', 'BP')->first())->typical_days->toBe(90)
        ->and(TaskTemplate::query()->count())->toBe(1);
});

test('the RAJUK template has the twelve items of docs/04 §3.9', function () {
    seedProjects();

    $template = TaskTemplate::query()->where('name', TaskTemplateSeeder::NAME)->firstOrFail();

    expect($template->items()->pluck('title')->all())->toHaveCount(12)
        ->and($template->items()->first()->title)->toBe('Site survey')
        ->and($template->items()->first()->checklist)->toHaveCount(3);
});

test('the Projects settings are seeded', function () {
    seedProjects();

    expect(Settings::get('projects.pm_can_view_all'))->toBeFalse()
        ->and(Settings::get('projects.archive_done_tasks_after_days'))->toBe(30)
        ->and(Settings::get('projects.default_status_on_conversion'))->toBe('CONTRACTED')
        ->and(Settings::get('projects.block_complete_with_open_checklist'))->toBeFalse();
});

test('projects permissions are seeded and granted per spec §4.1', function () {
    seedAccessControl();

    $grants = fn (string $role): array => Role::query()->where('code', $role)->firstOrFail()
        ->permissions()->where('module', 'projects')->pluck('name')->all();

    expect(Permission::query()->where('module', 'projects')->count())->toBe(29)
        ->and($grants('management'))->toContain('projects.projects.view_all', 'projects.projects.reopen', 'projects.tasks.view_all')
        ->not->toContain('projects.projects.view_own', 'projects.tasks.view_own')
        ->and($grants('project_manager'))->toContain('projects.projects.view_own', 'projects.tasks.view_project', 'projects.contracts.manage', 'projects.team.manage')
        ->not->toContain('projects.projects.view_all', 'projects.projects.reopen')
        ->and($grants('engineer'))->toEqualCanonicalizing(['projects.projects.view_own', 'projects.tasks.view_own', 'projects.tasks.create', 'projects.tasks.update', 'projects.tasks.complete', 'projects.approvals.view', 'projects.approvals.manage'])
        ->and($grants('accountant'))->toContain('projects.projects.view_all', 'projects.contracts.view')->not->toContain('projects.contracts.manage');
});
